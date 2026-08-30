<?php

declare(strict_types=1);

namespace Aitumalow\Database\Factories;

use Aitumalow\Models\Workflow;
use Aitumalow\Models\WorkflowRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowRevision> */
final class WorkflowRevisionFactory extends Factory
{
    protected $model = WorkflowRevision::class;

    public function definition(): array
    {
        $definition = [
            'version' => 1,
            'workflow_id' => 1,
            'trigger_node_id' => 1,
            'settings' => [],
            'node_name_map' => [],
            'nodes' => [],
            'edges' => [],
        ];

        return [
            'workflow_id' => Workflow::factory(),
            'version' => 1,
            'definition' => $definition,
            'definition_hash' => hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR)),
            'published_by_reference' => null,
            'published_at' => now(),
        ];
    }
}
