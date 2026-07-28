<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaxRecordStatus;
use App\Enums\TaxType;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\TaxRecord;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<TaxRecord>
 */
class TaxRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obligation_id' => null,
            'tax_type' => TaxType::Vat,
            'period_label' => 'Synthetic 2026-Q1',
            'currency' => 'AED',
            'taxable_amount' => '1000.00',
            'tax_amount' => '50.00',
            'status' => TaxRecordStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createForFirm(Firm $firm, Obligation $obligation, array $attributes = []): TaxRecord
    {
        if ($obligation->firm_id !== $firm->id) {
            throw new LogicException('The tax record obligation must belong to the selected firm.');
        }

        return app(FirmContext::class)->runForFirm($firm, function () use ($obligation, $attributes): TaxRecord {
            $taxRecord = $this->count(null)->state(['obligation_id' => $obligation->id])->create($attributes);

            if (! $taxRecord instanceof TaxRecord) {
                throw new LogicException('The tax record factory did not create one tax record.');
            }

            return $taxRecord;
        });
    }
}
