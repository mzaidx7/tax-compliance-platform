<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Feature;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureFlagOverride>
 */
class FeatureFlagOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature' => Feature::ComplianceOperations,
            'enabled' => true,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
