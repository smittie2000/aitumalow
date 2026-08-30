<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use RuntimeException;
use Workflow\UpdateMethod;
use Workflow\V2\Attributes\Type;
use Workflow\V2\Support\ActivityOptions;
use Workflow\V2\Workflow;

#[Type('aitumalow.graph.v1')]
final class DynamicGraphWorkflow extends Workflow
{
    private ?int $waitingNodeId = null;

    /** @var list<string> */
    private array $waitingCommands = [];

    /** @var array{name: string, node_id: int, payload: array<string, mixed>, scope: array<string, string|null>}|null */
    private ?array $pendingCommand = null;

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, string|null>  $scope
     * @param  array<string, mixed>  $runMetadata
     * @return array<string, array<string, array<int, array<string, mixed>>>>
     */
    public function handle(
        array $snapshot,
        array $payload,
        array $scope,
        int $projectionRunId,
        ?int $stopAfterNodeId = null,
        array $runMetadata = [],
    ): array {
        if (($snapshot['version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported Aitumalow workflow snapshot version.');
        }

        $projectionRunId = Workflow::activity(
            EnsureRunProjectionActivity::class,
            new ActivityOptions(maxAttempts: 3, backoff: 1),
            $snapshot,
            $payload,
            $scope,
            $projectionRunId,
            $runMetadata,
        );

        $nodes = [];
        foreach (is_array($snapshot['nodes'] ?? null) ? $snapshot['nodes'] : [] as $node) {
            if (is_array($node)) {
                $nodes[(int) ($node['id'] ?? 0)] = $node;
            }
        }
        $triggerNodeId = (int) ($snapshot['trigger_node_id'] ?? 0);
        $trigger = $nodes[$triggerNodeId] ?? null;

        if (! is_array($trigger)) {
            throw new RuntimeException('Workflow snapshot has no trigger node.');
        }

        $context = [];
        $triggerOutput = $this->executeNode(
            $trigger,
            $payload !== [] ? $payload : [[]],
            $payload,
            $context,
            $scope,
            $projectionRunId,
            $snapshot,
            $runMetadata,
        );
        $context['node:'.$triggerNodeId] = $triggerOutput;

        if ($stopAfterNodeId === $triggerNodeId) {
            return $context;
        }

        $edgeMap = $this->edgeMap(array_values(
            is_array($snapshot['edges'] ?? null) ? $snapshot['edges'] : [],
        ));
        $queue = $this->downstream($edgeMap, $triggerNodeId, $triggerOutput);
        $pendingInputs = [];
        $steps = 0;

        while ($queue !== []) {
            if (++$steps > 10000) {
                throw new RuntimeException('Workflow exceeded the deterministic 10,000-step safety limit.');
            }

            $task = array_shift($queue);
            $nodeId = (int) $task['node_id'];
            $node = $nodes[$nodeId] ?? null;

            if (! is_array($node) || ($node['type'] ?? null) === 'annotation') {
                continue;
            }

            $inputPorts = is_array($node['input_ports'] ?? null) ? $node['input_ports'] : [];
            if (count($inputPorts) > 1) {
                $pendingInputs[$nodeId][$task['target_port']] = $task['items'];
                $incomingPorts = $this->incomingPorts($nodeId, $snapshot['edges'] ?? []);

                foreach ($incomingPorts as $port) {
                    if (! array_key_exists($port, $pendingInputs[$nodeId])) {
                        continue 2;
                    }
                }

                $items = [];
                foreach ($pendingInputs[$nodeId] as $portItems) {
                    $items = [...$items, ...$portItems];
                }
                unset($pendingInputs[$nodeId]);
            } else {
                $items = $task['items'];
            }

            if (($node['key'] ?? null) === 'core.delay') {
                $delay = $this->delaySeconds($node['config'] ?? []);
                if ($delay > 0) {
                    Workflow::timer($delay);
                }
            }

            if (($node['key'] ?? null) === 'core.wait_resume') {
                $timeout = max(0, (int) (($node['config']['timeout_seconds'] ?? 0)));
                Workflow::activity(
                    ProjectRunWaitingActivity::class,
                    new ActivityOptions(maxAttempts: 3, backoff: 1),
                    $projectionRunId,
                    $nodeId,
                    is_string($node['config']['state_key'] ?? null)
                        ? $node['config']['state_key']
                        : null,
                );
                $this->waitingNodeId = $nodeId;
                $this->waitingCommands = $this->commandKeys($node['config'] ?? []);
                if (($this->pendingCommand['node_id'] ?? null) !== $nodeId) {
                    $this->pendingCommand = null;
                }
                $received = Workflow::await(
                    fn (): bool => $this->pendingCommand !== null,
                    $timeout > 0 ? $timeout : null,
                    'aitumalow.command.'.$nodeId,
                );
                $command = $this->pendingCommand;
                $this->waitingNodeId = null;
                $this->waitingCommands = [];
                $this->pendingCommand = null;
                Workflow::activity(
                    ProjectRunWaitingActivity::class,
                    new ActivityOptions(maxAttempts: 3, backoff: 1),
                    $projectionRunId,
                    null,
                    null,
                );
                $items = ! $received || $command === null
                    ? [['timed_out' => true]]
                    : [$command['payload']];
                if ($command !== null) {
                    $scope = $command['scope'];
                }
                $node['config']['resume_port'] = $command === null ? 'timeout' : $command['name'];
            }

            $output = $this->executeNode(
                $node,
                $items,
                $payload,
                $context,
                $scope,
                $projectionRunId,
                $snapshot,
                $runMetadata,
            );
            $context['node:'.$nodeId] = $output;

            if ($stopAfterNodeId === $nodeId) {
                return $context;
            }

            $queue = [...$queue, ...$this->downstream($edgeMap, $nodeId, $output)];
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $scope
     * @return array{accepted: true, command: string, node_id: int}
     */
    #[UpdateMethod('command')]
    public function command(string $name, int $expectedNodeId, array $payload = [], array $scope = []): array
    {
        if ($this->waitingNodeId !== null && $expectedNodeId !== $this->waitingNodeId) {
            throw new RuntimeException(
                "The Aitumalow run is waiting at node {$this->waitingNodeId}, not {$expectedNodeId}.",
            );
        }

        if ($this->waitingCommands !== [] && ! in_array($name, $this->waitingCommands, true)) {
            throw new RuntimeException("Command [{$name}] is not accepted by node {$this->waitingNodeId}.");
        }

        $this->pendingCommand = [
            'name' => $name,
            'node_id' => $expectedNodeId,
            'payload' => $payload,
            'scope' => [
                'tenant_reference' => is_string($scope['tenant_reference'] ?? null) ? $scope['tenant_reference'] : null,
                'principal_reference' => is_string($scope['principal_reference'] ?? null) ? $scope['principal_reference'] : null,
            ],
        ];

        return [
            'accepted' => true,
            'command' => $name,
            'node_id' => $expectedNodeId,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $context
     * @param  array<string, string|null>  $scope
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $runMetadata
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function executeNode(
        array $node,
        array $items,
        array $payload,
        array $context,
        array $scope,
        int $projectionRunId,
        array $snapshot,
        array $runMetadata,
    ): array {
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];
        $settings = is_array($snapshot['settings'] ?? null) ? $snapshot['settings'] : [];
        $retries = max(0, (int) ($config['retry_count'] ?? $settings['retry_count'] ?? 0));
        $delayMs = max(0, (int) ($config['retry_delay_ms'] ?? 1000));

        return Workflow::activity(
            ExecuteCapabilityActivity::class,
            new ActivityOptions(
                queue: is_string($settings['queue'] ?? null) ? $settings['queue'] : null,
                maxAttempts: $retries + 1,
                backoff: max(1, (int) ceil($delayMs / 1000)),
            ),
            $node,
            $items,
            $payload,
            $context,
            $scope,
            $projectionRunId,
            $snapshot['workflow_id'],
            (bool) ($snapshot['test_mode'] ?? false),
            is_array($snapshot['node_name_map'] ?? null) ? $snapshot['node_name_map'] : [],
            $runMetadata,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $edges
     * @return array<string, list<array{node_id: int, target_port: string}>>
     */
    private function edgeMap(array $edges): array
    {
        $map = [];

        foreach ($edges as $edge) {
            $key = $edge['source_node_id'].'_'.$edge['source_port'];
            $map[$key][] = [
                'node_id' => (int) $edge['target_node_id'],
                'target_port' => (string) $edge['target_port'],
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, list<array{node_id: int, target_port: string}>>  $edgeMap
     * @param  array<string, array<int, array<string, mixed>>>  $output
     * @return list<array{node_id: int, target_port: string, items: array<int, array<string, mixed>>}>
     */
    private function downstream(array $edgeMap, int $nodeId, array $output): array
    {
        $tasks = [];

        foreach ($output as $port => $items) {
            if ($items === []) {
                continue;
            }
            foreach ($edgeMap[$nodeId.'_'.$port] ?? [] as $target) {
                $tasks[] = [...$target, 'items' => $items];
            }
        }

        return $tasks;
    }

    /**
     * @param  list<array<string, mixed>>  $edges
     * @return list<string>
     */
    private function incomingPorts(int $nodeId, array $edges): array
    {
        $ports = [];
        foreach ($edges as $edge) {
            if ((int) ($edge['target_node_id'] ?? 0) === $nodeId) {
                $ports[] = (string) ($edge['target_port'] ?? 'main');
            }
        }

        return array_values(array_unique($ports));
    }

    /** @param array<string, mixed> $config */
    private function delaySeconds(array $config): int
    {
        $value = max(0, (int) ($config['delay_value'] ?? 0));

        return match ($config['delay_type'] ?? 'seconds') {
            'minutes' => $value * 60,
            'hours' => $value * 3600,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function commandKeys(array $config): array
    {
        $commands = [];
        foreach ($config['commands'] ?? [] as $command) {
            $key = is_array($command) ? ($command['key'] ?? null) : null;
            if (is_string($key) && preg_match('/\A[a-z][a-z0-9_-]*\z/', $key)) {
                $commands[] = $key;
            }
        }

        return $commands === [] ? ['resume'] : array_values(array_unique($commands));
    }
}
