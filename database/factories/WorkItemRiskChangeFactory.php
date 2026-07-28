<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RiskLevel;
use App\Models\User;
use App\Models\WorkItemRiskChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkItemRiskChange>
 */
class WorkItemRiskChangeFactory extends Factory
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
            'previous_risk_status' => null,
            'new_risk_status' => RiskLevel::Low,
            'changed_by' => User::factory(),
            'reason' => 'Synthetic risk change reason.',
            'changed_at' => now('UTC'),
        ];
    }
}
