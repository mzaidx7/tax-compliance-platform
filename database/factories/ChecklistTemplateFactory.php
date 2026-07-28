<?php

namespace Database\Factories;

use App\Models\ChecklistTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistTemplate>
 */
class ChecklistTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_key' => fake()->slug(3),
            'name' => fake()->words(4, true),
            'created_by' => User::factory(),
        ];
    }
}
