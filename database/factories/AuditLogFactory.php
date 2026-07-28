<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'action' => 'test.recorded',
            'correlation_id' => (string) Str::ulid(),
        ];
    }
}
