<?php

namespace Database\Factories;

use App\Enums\FirmMembershipStatus;
use App\Enums\FirmRole;
use App\Models\Firm;
use App\Models\FirmMembership;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<FirmMembership>
 */
class FirmMembershipFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => FirmRole::Preparer,
            'status' => FirmMembershipStatus::Active,
            'joined_at' => now(),
            'suspended_at' => null,
            'revoked_at' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => FirmMembershipStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => FirmMembershipStatus::Revoked,
            'suspended_at' => null,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Persist one membership through a trusted firm context.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForFirm(Firm $firm, array $attributes = []): FirmMembership
    {
        return app(FirmContext::class)->runForFirm($firm, function () use ($firm, $attributes): FirmMembership {
            $membership = $this
                ->count(null)
                ->state(['firm_id' => $firm->id])
                ->create($attributes);

            if (! $membership instanceof FirmMembership) {
                throw new LogicException('The membership factory did not create one membership.');
            }

            return $membership;
        });
    }
}
