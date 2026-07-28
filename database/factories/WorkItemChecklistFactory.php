<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkItemChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkItemChecklist>
 */
class WorkItemChecklistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_item_id' => null,
            'checklist_version_id' => null,
            'applied_by' => User::factory(),
            'applied_at' => now(),
        ];
    }
}
