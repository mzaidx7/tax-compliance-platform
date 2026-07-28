<?php

namespace Database\Factories;

use App\Models\ChecklistItemCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItemCompletion>
 */
class ChecklistItemCompletionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_item_checklist_id' => null,
            'checklist_item_id' => null,
            'completed_by' => User::factory(),
            'evidence_note' => 'Synthetic checklist completion evidence.',
            'completed_at' => now(),
        ];
    }
}
