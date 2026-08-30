<?php

namespace Aitumalow\Database\Factories;

use Aitumalow\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workflow> */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => false,
            'settings' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
