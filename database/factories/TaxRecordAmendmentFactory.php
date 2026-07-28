<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaxRecordStatus;
use App\Models\TaxRecordAmendment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRecordAmendment>
 */
class TaxRecordAmendmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_record_id' => null,
            'previous_status' => null,
            'previous_taxable_amount' => null,
            'previous_tax_amount' => null,
            'new_status' => TaxRecordStatus::Draft,
            'new_taxable_amount' => '1000.00',
            'new_tax_amount' => '50.00',
            'amended_by' => User::factory(),
            'reason' => 'Synthetic tax amendment reason.',
            'amended_at' => now('UTC'),
        ];
    }
}
