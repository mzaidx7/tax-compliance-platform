<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationRequestStatus;
use App\Models\Firm;
use App\Models\NotificationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationRequest>
 */
class NotificationRequestFactory extends Factory
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
            'recipient_user_id' => User::factory(),
            'template_key' => 'synthetic_test_notification',
            'template_version' => 1,
            'channel' => NotificationChannel::Mail,
            'deterministic_key' => hash('sha256', fake()->uuid()),
            'trigger_type' => null,
            'trigger_id' => null,
            'scheduled_at' => now(),
            'status' => NotificationRequestStatus::Queued,
            'final_status' => null,
            'attempt_count' => 0,
            'correlation_id' => (string) Str::ulid(),
            'completed_at' => null,
        ];
    }
}
