<?php

namespace Database\Factories;

use App\Enums\NotificationAttemptStatus;
use App\Models\Firm;
use App\Models\NotificationAttempt;
use App\Models\NotificationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationAttempt>
 */
class NotificationAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firm_id' => Firm::factory(),
            'notification_id' => NotificationRequest::factory(),
            'attempt_number' => 1,
            'status' => NotificationAttemptStatus::Delivered,
            'provider_reference' => null,
            'failure_reason' => null,
            'attempted_at' => now(),
        ];
    }
}
