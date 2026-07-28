<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\CalculatorGoldenCase;
use App\Models\CalculatorGoldenCaseVerification;
use App\Models\Obligation;
use App\Models\User;
use App\Support\CalculatorRegistry;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class VerifyGoldenCase
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CalculatorRegistry $calculatorRegistry,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, CalculatorGoldenCase $case): CalculatorGoldenCaseVerification
    {
        $this->authorize($actor, $case);
        $case->loadMissing('caseSet');
        if ($case->prepared_by === $actor->id) {
            throw ValidationException::withMessages(['verifier' => 'The verifier must be different from the case preparer.']);
        }
        if ($case->caseSet->status !== 'draft') {
            throw ValidationException::withMessages(['caseSet' => 'Only cases in a draft set can be verified.']);
        }
        $calculator = $this->calculatorRegistry->get($case->caseSet->calculator_key);
        $parameters = $calculator->validateParameters($case->parameter_snapshot);
        $result = $calculator->calculate($case->input_snapshot, $parameters);
        $observed = ['statutory_due_date' => $result->statutoryDueDate];
        $passed = $observed === $case->expected_result_snapshot;

        return DB::transaction(function () use ($actor, $case, $observed, $result, $passed): CalculatorGoldenCaseVerification {
            $verification = CalculatorGoldenCaseVerification::query()->create([
                'calculator_golden_case_id' => $case->id,
                'observed_result_snapshot' => $observed,
                'calculation_explanation' => $result->explanation,
                'passed' => $passed,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
            $this->recordAudit->handle(
                action: 'calculator_golden_case.verified',
                actor: $actor,
                auditable: $verification,
                after: ['case_id' => $case->id, 'passed' => $passed],
            );

            return $verification->refresh();
        }, 3);
    }

    private function authorize(User $actor, CalculatorGoldenCase $case): void
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($case->firm_id !== $firmId) {
            throw new AuthorizationException('The golden case does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);
    }
}
