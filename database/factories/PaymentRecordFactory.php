<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Firm;
use App\Models\Obligation;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<PaymentRecord>
 */
class PaymentRecordFactory extends Factory
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
            'status' => PaymentStatus::Pending,
            'payment_reference' => null,
            'paid_on' => null,
            'created_by' => User::factory(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function createForFirm(Firm $firm, Obligation $obligation, array $attributes = []): PaymentRecord
    {
        if ($obligation->firm_id !== $firm->id) {
            throw new LogicException('The payment record obligation must belong to the selected firm.');
        }

        return app(FirmContext::class)->runForFirm($firm, function () use ($obligation, $attributes): PaymentRecord {
            $paymentRecord = $this->count(null)->state(['obligation_id' => $obligation->id])->create($attributes);

            if (! $paymentRecord instanceof PaymentRecord) {
                throw new LogicException('The payment record factory did not create one payment record.');
            }

            return $paymentRecord;
        });
    }
}
