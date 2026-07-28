<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FilingStatus;
use App\Models\FilingRecord;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<FilingRecord>
 */
class FilingRecordFactory extends Factory
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
            'status' => FilingStatus::NotFiled,
            'filing_reference' => null,
            'filed_on' => null,
            'created_by' => User::factory(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createForFirm(Firm $firm, Obligation $obligation, array $attributes = []): FilingRecord
    {
        if ($obligation->firm_id !== $firm->id) {
            throw new LogicException('The filing record obligation must belong to the selected firm.');
        }

        return app(FirmContext::class)->runForFirm($firm, function () use ($obligation, $attributes): FilingRecord {
            $filingRecord = $this->count(null)->state(['obligation_id' => $obligation->id])->create($attributes);

            if (! $filingRecord instanceof FilingRecord) {
                throw new LogicException('The filing record factory did not create one filing record.');
            }

            return $filingRecord;
        });
    }
}
