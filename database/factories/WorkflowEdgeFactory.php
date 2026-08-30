<?php

namespace Aitumalow\Database\Factories;

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowEdge;
use Aitumalow\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowEdge> */
class WorkflowEdgeFactory extends Factory
{
    protected $model = WorkflowEdge::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'source_node_id' => WorkflowNode::factory(),
            'source_port' => 'main',
            'target_node_id' => WorkflowNode::factory(),
            'target_port' => 'main',
        ];
    }

    public function fromPort(string $port): static
    {
        return $this->state(['source_port' => $port]);
    }

    public function toPort(string $port): static
    {
        return $this->state(['target_port' => $port]);
    }
}
