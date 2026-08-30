<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\Contracts\ExpressionEvaluatorInterface;
use Aitumalow\DTOs\ExecutionContext;
use Aitumalow\DTOs\ExecutionScope;
use Aitumalow\DTOs\NodeInput;
use Aitumalow\Engine\NodeRunner;
use Aitumalow\Enums\NodeRunStatus;
use Aitumalow\Events\NodeExecuted;
use Aitumalow\Events\NodeFailed;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Registry\NodeRegistry;
use Throwable;
use Workflow\V2\Activity;
use Workflow\V2\Attributes\Type;

#[Type('aitumalow.capability.v1')]
final class ExecuteCapabilityActivity extends Activity
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly NodeRunner $runner,
        private readonly ExpressionEvaluatorInterface $expressions,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $initialPayload
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $outputs
     * @param  array<string, string|null>  $scope
     * @param  array<string, int>  $nodeNameMap
     * @param  array<string, mixed>  $runMetadata
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function handle(
        array $node,
        array $items,
        array $initialPayload,
        array $outputs,
        array $scope,
        int $projectionRunId,
        int $workflowId,
        bool $testMode = false,
        array $nodeNameMap = [],
        array $runMetadata = [],
    ): array {
        $pinnedData = is_array($node['pinned_data'] ?? null) ? $node['pinned_data'] : [];
        if ($testMode && is_array($pinnedData['input'] ?? null)) {
            $items = $pinnedData['input'];
        }

        $nodeId = (int) ($node['id'] ?? 0);
        $context = new ExecutionContext(
            workflowRunId: $projectionRunId,
            workflowId: $workflowId,
            initialPayload: $initialPayload,
            scope: ExecutionScope::fromArray($scope),
            durableActivityId: $this->activityId(),
            subjectType: is_string($runMetadata['subject_type'] ?? null) ? $runMetadata['subject_type'] : null,
            subjectReference: is_string($runMetadata['subject_reference'] ?? null) ? $runMetadata['subject_reference'] : null,
            subjectContext: is_array($runMetadata['subject_context'] ?? null) ? $runMetadata['subject_context'] : [],
            subjectFreshness: is_string($runMetadata['subject_freshness'] ?? null) ? $runMetadata['subject_freshness'] : null,
            executorReference: is_string($runMetadata['executor_reference'] ?? null) ? $runMetadata['executor_reference'] : null,
        );
        $normalizedOutputs = [];
        foreach ($outputs as $key => $ports) {
            $normalizedOutputs[(int) str_replace('node:', '', (string) $key)] = $ports;
        }
        $context->restoreOutputs($normalizedOutputs);

        $nodeRun = WorkflowNodeRun::query()->firstOrNew([
            'durable_activity_id' => $this->activityId(),
        ]);
        $nodeRun->fill([
            'workflow_run_id' => $projectionRunId,
            'node_id' => $nodeId,
            'status' => NodeRunStatus::Running,
            'input' => $items,
            'attempts' => $this->attemptCount(),
            'executed_at' => now(),
            'error_message' => null,
        ])->save();

        $startedAt = microtime(true);

        try {
            if ($testMode && is_array($pinnedData['output'] ?? null)) {
                $nodeRun->update([
                    'status' => NodeRunStatus::Completed,
                    'output' => $pinnedData['output'],
                    'duration_ms' => 0,
                    'attempts' => $this->attemptCount(),
                ]);
                event(new NodeExecuted($nodeRun));

                return $pinnedData['output'];
            }

            $variables = $context->toVariables($nodeNameMap, $items[0] ?? []);
            $config = $this->expressions->resolveConfig(
                is_array($node['config'] ?? null) ? $node['config'] : [],
                $variables,
            );
            $instance = $this->registry->resolve((string) ($node['key'] ?? ''));
            $output = $this->runner->run(
                $instance,
                new NodeInput($items, $context),
                $config,
            );

            $nodeRun->update([
                'status' => NodeRunStatus::Completed,
                'output' => $output->portItems,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'attempts' => $this->attemptCount(),
            ]);
            event(new NodeExecuted($nodeRun));

            return $output->portItems;
        } catch (Throwable $exception) {
            $nodeRun->update([
                'status' => NodeRunStatus::Failed,
                'error_message' => $exception->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'attempts' => $this->attemptCount(),
            ]);
            event(new NodeFailed($nodeRun, $exception));

            throw $exception;
        }
    }
}
