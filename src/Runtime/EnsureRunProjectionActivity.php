<?php

declare(strict_types=1);

namespace Aitumalow\Runtime;

use Aitumalow\Enums\RunStatus;
use Aitumalow\Models\WorkflowRun;
use Workflow\V2\Activity;
use Workflow\V2\Attributes\Type;

#[Type('aitumalow.run_projection.v1')]
final class EnsureRunProjectionActivity extends Activity
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array<string, mixed>>  $payload
     * @param  array<string, string|null>  $scope
     * @param  array<string, mixed>  $runMetadata
     */
    public function handle(array $snapshot, array $payload, array $scope, int $projectionRunId, array $runMetadata = []): int
    {
        $attributes = [
            'workflow_id' => (int) $snapshot['workflow_id'],
            'workflow_revision_id' => (int) $snapshot['workflow_revision_id'],
            'durable_workflow_id' => $this->workflowId(),
            'durable_run_id' => $this->runId(),
            'status' => RunStatus::Running,
            'trigger_node_id' => (int) $snapshot['trigger_node_id'],
            'execution_scope' => $scope,
            'initial_payload' => $payload,
            'started_at' => now(),
            'subject_type' => $runMetadata['subject_type'] ?? null,
            'subject_reference' => $runMetadata['subject_reference'] ?? null,
            'subject_context' => $runMetadata['subject_context'] ?? null,
            'subject_freshness' => $runMetadata['subject_freshness'] ?? null,
            'executor_reference' => $runMetadata['executor_reference'] ?? null,
            'idempotency_scope' => $runMetadata['idempotency_scope'] ?? null,
            'idempotency_key' => $runMetadata['idempotency_key'] ?? null,
        ];

        if ($projectionRunId > 0) {
            $run = WorkflowRun::query()->findOrFail($projectionRunId);
            $run->update($attributes);

            return $run->id;
        }

        return WorkflowRun::query()->firstOrCreate(
            ['durable_run_id' => $this->runId()],
            $attributes,
        )->id;
    }
}
