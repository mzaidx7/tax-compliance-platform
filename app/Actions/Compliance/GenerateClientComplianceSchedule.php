<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Actions\Audit\RecordAudit;
use App\Calculators\CorporateTaxFilingDeadlineCalculator;
use App\Calculators\VatFilingDeadlineCalculator;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Enums\TaxPeriodStatus;
use App\Enums\TaxRegistrationStatus;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\Obligation;
use App\Models\TaxPeriod;
use App\Models\TaxRegistration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class GenerateClientComplianceSchedule
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(User $actor, Client $client, int $monthsAhead = 18): int
    {
        Gate::forUser($actor)->authorize('create', Obligation::class);

        return DB::transaction(function () use ($actor, $client, $monthsAhead): int {
            $created = 0;
            $client->refresh();

            if ($client->vat_period_starts_on !== null && $client->vat_period_ends_on !== null) {
                $vat = $this->registration($actor, $client, TaxType::Vat, $client->vat_trn);
                if ($vat !== null) {
                    $created += $this->generateVat($actor, $client, $vat, $monthsAhead);
                }
            }

            if ($client->corporate_tax_period_starts_on !== null && $client->corporate_tax_period_ends_on !== null) {
                $ct = $this->registration($actor, $client, TaxType::CorporateTax, $client->corporate_tax_trn);
                if ($ct !== null) {
                    $created += $this->generateCorporateTax($actor, $client, $ct);
                }
            }

            if ($created > 0) {
                $this->recordAudit->handle(
                    action: 'client.compliance_schedule_generated',
                    actor: $actor,
                    auditable: $client,
                    after: ['created_obligations' => $created, 'months_ahead' => $monthsAhead],
                );
            }

            return $created;
        }, 3);
    }

    private function registration(User $actor, Client $client, TaxType $type, ?string $number): ?TaxRegistration
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        return TaxRegistration::query()->firstOrCreate(
            ['client_id' => $client->id, 'tax_type' => $type],
            [
                'registration_number' => trim($number),
                'registration_number_normalized' => strtoupper(preg_replace('/\s+/', '', trim($number)) ?? trim($number)),
                'status' => TaxRegistrationStatus::Active,
                'created_by' => $actor->id,
            ],
        );
    }

    private function generateVat(User $actor, Client $client, TaxRegistration $registration, int $monthsAhead): int
    {
        $start = CarbonImmutable::parse($client->vat_period_starts_on->toDateString());
        $end = CarbonImmutable::parse($client->vat_period_ends_on->toDateString());
        $months = strtolower((string) $client->vat_frequency) === 'monthly' ? 1 : 3;
        $limit = $end->addMonthsNoOverflow(max(1, $monthsAhead));
        $created = 0;
        $calculator = new VatFilingDeadlineCalculator;

        while ($end->lte($limit)) {
            $period = $this->period($actor, $registration, $start, $end, 'VAT');
            $result = $calculator->calculate(['tax_period_end' => $end->toDateString()], []);
            $created += $this->obligation($actor, $client, $period, 'VAT Return', $result->statutoryDueDate, $result->explanation, 'vat');
            $start = $start->addMonthsNoOverflow($months);
            $end = $end->addMonthsNoOverflow($months);
        }

        return $created;
    }

    private function generateCorporateTax(User $actor, Client $client, TaxRegistration $registration): int
    {
        $start = CarbonImmutable::parse($client->corporate_tax_period_starts_on->toDateString());
        $end = CarbonImmutable::parse($client->corporate_tax_period_ends_on->toDateString());
        $calculator = new CorporateTaxFilingDeadlineCalculator;
        $period = $this->period($actor, $registration, $start, $end, 'Corporate Tax');
        $result = $calculator->calculate(['tax_period_end' => $end->toDateString()], []);

        return $this->obligation($actor, $client, $period, 'Corporate Tax Return', $result->statutoryDueDate, $result->explanation, 'corporate-tax');
    }

    private function period(User $actor, TaxRegistration $registration, CarbonImmutable $start, CarbonImmutable $end, string $name): TaxPeriod
    {
        $existing = TaxPeriod::query()
            ->where('tax_registration_id', $registration->id)
            ->whereDate('starts_on', $start->toDateString())
            ->whereDate('ends_on', $end->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return TaxPeriod::query()->create([
            'tax_registration_id' => $registration->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'label' => "{$name} {$start->format('M Y')} to {$end->format('M Y')}",
            'status' => TaxPeriodStatus::Open,
            'created_by' => $actor->id,
        ]);
    }

    private function obligation(User $actor, Client $client, TaxPeriod $period, string $type, string $dueDate, string $explanation, string $keyPrefix): int
    {
        $key = hash('sha256', "client-schedule:v1:{$client->firm_id}:{$client->id}:{$period->id}:{$type}");
        $obligation = Obligation::query()->firstOrCreate(
            ['generation_key' => $key],
            [
                'client_id' => $client->id,
                'tax_period_id' => $period->id,
                'calculation_input_snapshot' => ['tax_period_end' => $period->ends_on->toDateString()],
                'calculation_parameter_snapshot' => [],
                'calculation_result_snapshot' => ['statutory_due_date' => $dueDate],
                'calculation_explanation' => $explanation,
                'obligation_type' => $type,
                'period_label' => $period->label,
                'statutory_due_date' => $dueDate,
                'effective_due_date' => $dueDate,
                'origin' => ObligationOrigin::GovernedRule,
                'status' => ObligationStatus::Open,
                'source_reference' => $keyPrefix === 'vat'
                    ? 'https://tax.gov.ae/DataFolder/Files/Pdf/VAT%20Returns%20User%20GuideEnglishV40%2015%2008%202021%20SEP2021.pdf'
                    : 'https://mof.gov.ae/en/public-finance/tax/corporate-tax/',
                'last_verified_on' => today(),
                'verified_by' => $actor->id,
                'created_by' => $actor->id,
            ],
        );

        return $obligation->wasRecentlyCreated ? 1 : 0;
    }
}
