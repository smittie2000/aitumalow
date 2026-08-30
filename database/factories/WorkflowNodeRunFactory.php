<?php

namespace Aitumalow\Database\Factories;

use Aitumalow\Enums\NodeRunStatus;
use Aitumalow\Models\WorkflowNode;
use Aitumalow\Models\WorkflowNodeRun;
use Aitumalow\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowNodeRun> */
class WorkflowNodeRunFactory extends Factory
{
    protected $model = WorkflowNodeRun::class;

    public function definition(): array
    {
        return [
            'workflow_run_id' => WorkflowRun::factory(),
            'node_id' => WorkflowNode::factory(),
            'status' => NodeRunStatus::Pending,
            'input' => null,
            'output' => null,
            'error_message' => null,
            'duration_ms' => null,
            'attempts' => 0,
            'executed_at' => null,
        ];
    }

    public function completed(array $output = []): static
    {
        return $this->state([
            'status' => NodeRunStatus::Completed,
            'output' => $output,
            'duration_ms' => fake()->numberBetween(10, 5000),
            'executed_at' => now(),
        ]);
    }

    public function failed(string $error = 'Node execution failed'): static
    {
        return $this->state([
            'status' => NodeRunStatus::Failed,
            'error_message' => $error,
            'executed_at' => now(),
        ]);
    }
}
