<?php

namespace Aitumalow\Database\Factories;

use Aitumalow\Models\WorkflowFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowFolder> */
class WorkflowFolderFactory extends Factory
{
    protected $model = WorkflowFolder::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'parent_id' => null,
        ];
    }

    public function childOf(WorkflowFolder $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
