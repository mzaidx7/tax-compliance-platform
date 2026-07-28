<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowDefinition>
 */
class WorkflowDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'definition_key' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'version' => 1,
            'status' => 'published',
            'published_by' => User::factory(),
            'published_at' => now('UTC'),
        ];
    }
}
