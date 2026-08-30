<?php

namespace Aitumalow\Database\Factories;

use Aitumalow\Enums\RunStatus;
use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowRevision;
use Aitumalow\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowRun> */
class WorkflowRunFactory extends Factory
{
    protected $model = WorkflowRun::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'workflow_revision_id' => fn (array $attributes): int => WorkflowRevision::factory()->create([
                'workflow_id' => $attributes['workflow_id'],
            ])->id,
            'status' => RunStatus::Pending,
            'trigger_node_id' => null,
            'waiting_node_id' => null,
            'waiting_state' => null,
            'durable_workflow_id' => null,
            'durable_run_id' => null,
            'execution_scope' => [
                'tenant_reference' => null,
                'principal_reference' => null,
            ],
            'subject_type' => null,
            'subject_reference' => null,
            'subject_context' => null,
            'subject_freshness' => null,
            'executor_reference' => null,
            'idempotency_scope' => null,
            'idempotency_key' => null,
            'initial_payload' => null,
            'context' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => RunStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => RunStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $error = 'Something went wrong'): static
    {
        return $this->state([
            'status' => RunStatus::Failed,
            'error_message' => $error,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function waiting(): static
    {
        return $this->state([
            'status' => RunStatus::Waiting,
            'started_at' => now(),
        ]);
    }
}
