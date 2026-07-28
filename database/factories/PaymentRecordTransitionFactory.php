<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\PaymentRecordTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRecordTransition>
 */
class PaymentRecordTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_record_id' => null,
            'from_status' => null,
            'to_status' => PaymentStatus::Pending,
            'transitioned_by' => User::factory(),
            'reason' => 'Synthetic payment transition reason.',
            'transitioned_at' => now('UTC'),
        ];
    }
}
