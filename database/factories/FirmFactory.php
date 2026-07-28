<?php

namespace Database\Factories;

use App\Enums\FirmStatus;
use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Firm>
 */
class FirmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->bothify('firm-####-????'),
            'timezone' => 'Asia/Dubai',
            'status' => FirmStatus::Active,
            'suspended_at' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => FirmStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
