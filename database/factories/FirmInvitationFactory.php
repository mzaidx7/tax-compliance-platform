<?php

namespace Database\Factories;

use App\Enums\FirmInvitationStatus;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\FirmInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirmInvitation>
 */
class FirmInvitationFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'role' => FirmRole::Preparer,
            'status' => FirmInvitationStatus::Pending,
            'token_hash' => hash('sha256', fake()->uuid()),
            'expires_at' => now()->addDays(3),
            'invited_by' => User::factory(),
        ];
    }
}
