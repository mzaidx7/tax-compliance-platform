<?php

namespace Database\Factories;

use App\Models\ChecklistVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistVersion>
 */
class ChecklistVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_template_id' => null,
            'version' => 1,
            'status' => 'published',
            'published_by' => User::factory(),
            'published_at' => now(),
        ];
    }
}
