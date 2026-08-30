<?php

namespace Aitumalow\Database\Factories;

use Aitumalow\Models\WorkflowTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowTag> */
class WorkflowTagFactory extends Factory
{
    protected $model = WorkflowTag::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
