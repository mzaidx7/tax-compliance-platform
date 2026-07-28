<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FilingStatus;
use App\Models\FilingRecordTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilingRecordTransition>
 */
class FilingRecordTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filing_record_id' => null,
            'from_status' => null,
            'to_status' => FilingStatus::NotFiled,
            'transitioned_by' => User::factory(),
            'reason' => 'Synthetic filing transition reason.',
            'transitioned_at' => now('UTC'),
        ];
    }
}
