<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\GenerationRunStatus;
use App\Enums\RuleVersionStatus;
use App\Models\Client;
use App\Models\ClientServiceEnrollment;
use App\Models\Obligation;
use App\Models\ObligationGenerationRun;
use App\Models\ObligationRuleVersion;
use App\Models\TaxPeriod;
use App\Models\User;
use App\Support\CalculatorRegistry;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class PreviewGeneratedObligation
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CalculatorRegistry $calculatorRegistry,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param array{
     *   applicabilityDate: mixed,
     *   statutoryDueDate: mixed,
     *   internalTargetDate?: mixed,
     *   periodLabel: mixed
     * } $inputs
     */
    public function handle(
        User $actor,
        Client $client,
        ClientServiceEnrollment $serviceEnrollment,
        ?TaxPeriod $taxPeriod,
        ObligationRuleVersion $ruleVersion,
        array $inputs,
    ): ObligationGenerationRun {
        $firmId = $this->authorize($actor, $client, $serviceEnrollment, $taxPeriod, $ruleVersion);
        /** @var array{
         *   applicability_date: string,
         *   statutory_due_date: string,
         *   internal_target_date: string|null,
         *   period_label: string
         * } $validated
         */
        $validated = Validator::make(
            [
                'applicability_date' => $inputs['applicabilityDate'] ?? null,
                'statutory_due_date' => $inputs['statutoryDueDate'] ?? null,
                'internal_target_date' => $inputs['internalTargetDate'] ?? null,
                'period_label' => $inputs['periodLabel'] ?? null,
            ],
            [
                'applicability_date' => ['required', 'date_format:Y-m-d'],
                'statutory_due_date' => ['required', 'date_format:Y-m-d'],
                'internal_target_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:statutory_due_date'],
                'period_label' => ['required', 'string', 'max:100'],
            ],
        )->validate();

        $this->validateApplicability($client, $serviceEnrollment, $taxPeriod, $ruleVersion, $validated['applicability_date']);
        $calculator = $this->calculatorRegistry->get($ruleVersion->calculator_key);
        $parameters = $calculator->validateParameters($ruleVersion->parameters);
        $result = $calculator->calculate(
            ['statutory_due_date' => $validated['statutory_due_date']],
            $parameters,
        );
        $inputSnapshot = [
            'applicability_date' => $validated['applicability_date'],
            'period_label' => trim($validated['period_label']),
            'statutory_due_date' => $validated['statutory_due_date'],
            'internal_target_date' => $validated['internal_target_date'],
        ];
        $resultSnapshot = ['statutory_due_date' => $result->statutoryDueDate];
        $deterministicKey = $this->deterministicKey(
            $firmId,
            $client->id,
            $serviceEnrollment->id,
            $taxPeriod?->id,
            $ruleVersion->id,
            $inputSnapshot,
            $parameters,
        );

        return DB::transaction(function () use (
            $actor,
            $client,
            $serviceEnrollment,
            $taxPeriod,
            $ruleVersion,
            $inputSnapshot,
            $parameters,
            $resultSnapshot,
            $result,
            $deterministicKey,
        ): ObligationGenerationRun {
            $run = ObligationGenerationRun::query()->firstOrCreate(
                [
                    'status' => GenerationRunStatus::Preview,
                    'deterministic_key' => $deterministicKey,
                ],
                [
                    'preview_run_id' => null,
                    'client_id' => $client->id,
                    'client_service_enrollment_id' => $serviceEnrollment->id,
                    'tax_period_id' => $taxPeriod?->id,
                    'obligation_rule_version_id' => $ruleVersion->id,
                    'input_snapshot' => $inputSnapshot,
                    'parameter_snapshot' => $parameters,
                    'result_snapshot' => $resultSnapshot,
                    'statutory_due_date' => $result->statutoryDueDate,
                    'internal_target_date' => $inputSnapshot['internal_target_date'],
                    'calculation_explanation' => $result->explanation,
                    'created_by' => $actor->id,
                ],
            );

            if ($run->wasRecentlyCreated) {
                $this->recordAudit->handle(
                    action: 'obligation_generation.previewed',
                    actor: $actor,
                    auditable: $run,
                    after: [
                        'rule_version_id' => $ruleVersion->id,
                        'client_id' => $client->id,
                        'deterministic_key' => $deterministicKey,
                    ],
                );
            }

            return $run->refresh();
        }, 3);
    }

    private function authorize(
        User $actor,
        Client $client,
        ClientServiceEnrollment $serviceEnrollment,
        ?TaxPeriod $taxPeriod,
        ObligationRuleVersion $ruleVersion,
    ): string {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        foreach ([$client, $serviceEnrollment, $ruleVersion] as $record) {
            if ($record->firm_id !== $firmId) {
                throw new AuthorizationException('All generation inputs must belong to the active firm.');
            }
        }
        if ($taxPeriod !== null && $taxPeriod->firm_id !== $firmId) {
            throw new AuthorizationException('All generation inputs must belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);

        return $firmId;
    }

    private function validateApplicability(
        Client $client,
        ClientServiceEnrollment $serviceEnrollment,
        ?TaxPeriod $taxPeriod,
        ObligationRuleVersion $ruleVersion,
        string $applicabilityDate,
    ): void {
        if ($ruleVersion->status !== RuleVersionStatus::Published) {
            throw ValidationException::withMessages(['ruleVersion' => 'Only a published rule version can generate obligations.']);
        }

        $date = CarbonImmutable::parse($applicabilityDate);
        if (
            $date->lt($ruleVersion->effective_from)
            || ($ruleVersion->effective_to !== null && $date->gt($ruleVersion->effective_to))
        ) {
            throw ValidationException::withMessages(['applicabilityDate' => 'The date is outside the published rule version window.']);
        }
        if (
            $serviceEnrollment->client_id !== $client->id
            || $date->lt($serviceEnrollment->starts_on)
            || ($serviceEnrollment->ends_on !== null && $date->gt($serviceEnrollment->ends_on))
        ) {
            throw ValidationException::withMessages(['serviceEnrollment' => 'The selected service does not cover this client and applicability date.']);
        }

        if ($taxPeriod !== null) {
            $taxPeriod->loadMissing('registration');
            if (
                $taxPeriod->registration->client_id !== $client->id
                || $date->lt($taxPeriod->starts_on)
                || $date->gt($taxPeriod->ends_on)
            ) {
                throw ValidationException::withMessages(['taxPeriod' => 'The selected actual tax period does not cover this client and applicability date.']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $parameters
     */
    private function deterministicKey(
        string $firmId,
        string $clientId,
        string $serviceEnrollmentId,
        ?string $taxPeriodId,
        string $ruleVersionId,
        array $inputs,
        array $parameters,
    ): string {
        $payload = [
            'firm_id' => $firmId,
            'client_id' => $clientId,
            'service_enrollment_id' => $serviceEnrollmentId,
            'tax_period_id' => $taxPeriodId,
            'rule_version_id' => $ruleVersionId,
            'inputs' => $this->canonicalize($inputs),
            'parameters' => $this->canonicalize($parameters),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
