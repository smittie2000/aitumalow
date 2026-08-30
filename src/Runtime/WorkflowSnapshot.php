<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\Enums\NodeType;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowRevision;
use Aitumalow\Registry\NodeRegistry;
use RuntimeException;

final readonly class WorkflowSnapshot
{
    public function __construct(
        private NodeRegistry $registry,
    ) {}

    /** @return array<string, mixed> */
    public function capture(Workflow $workflow): array
    {
        return $this->captureDraft($workflow);
    }

    /** @return array<string, mixed> */
    public function captureDraft(Workflow $workflow): array
    {
        $workflow->load(['nodes', 'edges']);

        $nodes = $workflow->nodes
            ->sortBy('id')
            ->map(function (WorkflowNode $node): array {
                $definition = $this->registry->definition($node->node_key);
                $outputPorts = $definition['output_ports'] ?? ['main'];
                if ($node->node_key === 'core.wait_resume') {
                    $commandKeys = [];
                    $commands = $node->config['commands'] ?? null;
                    if (is_array($commands)) {
                        foreach ($commands as $command) {
                            if (is_array($command) && is_string($command['key'] ?? null)) {
                                $commandKeys[] = $command['key'];
                            }
                        }
                    }
                    $outputPorts = $commandKeys === [] ? ['resume', 'timeout'] : [...$commandKeys, 'timeout'];
                }

                return [
                    'id' => $node->id,
                    'key' => $node->node_key,
                    'name' => $node->name,
                    'type' => $node->type->value,
                    'config' => $node->config ?? [],
                    'pinned_data' => $node->pinned_data,
                    'input_ports' => $definition['input_ports'] ?? [],
                    'output_ports' => $outputPorts,
                ];
            })
            ->values()
            ->all();

        $trigger = $workflow->nodes->first(
            fn (WorkflowNode $node): bool => $node->type === NodeType::Trigger,
        );

        return [
            'version' => 1,
            'workflow_id' => $workflow->id,
            'trigger_node_id' => $trigger?->id,
            'settings' => array_replace([
                'queue' => config('aitumalow.queue', 'default'),
                'retry_count' => config('aitumalow.default_retry_count', 0),
                'retry_delay_ms' => config('aitumalow.default_retry_delay_ms', 1000),
            ], $workflow->settings ?? []),
            'node_name_map' => $workflow->nodes
                ->filter(fn (WorkflowNode $node): bool => filled($node->name))
                ->mapWithKeys(fn (WorkflowNode $node): array => [(string) $node->name => $node->id])
                ->all(),
            'nodes' => $nodes,
            'edges' => $workflow->edges
                ->sortBy(fn ($edge): string => implode(':', [
                    str_pad((string) $edge->source_node_id, 20, '0', STR_PAD_LEFT),
                    $edge->source_port,
                    str_pad((string) $edge->target_node_id, 20, '0', STR_PAD_LEFT),
                    $edge->target_port,
                ]))
                ->map(fn ($edge): array => [
                    'source_node_id' => $edge->source_node_id,
                    'source_port' => $edge->source_port,
                    'target_node_id' => $edge->target_node_id,
                    'target_port' => $edge->target_port,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function fromRevision(WorkflowRevision $revision): array
    {
        $definition = $revision->definition;

        if (! hash_equals($revision->definition_hash, $this->hash($definition))) {
            throw new RuntimeException("Aitumalow workflow revision {$revision->id} failed its definition hash check.");
        }

        return [
            ...$definition,
            'workflow_revision_id' => $revision->id,
            'workflow_revision_version' => $revision->version,
            'workflow_revision_hash' => $revision->definition_hash,
        ];
    }

    /** @param array<string, mixed> $definition */
    public function hash(array $definition): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($definition),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
