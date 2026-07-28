<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\GenerationRunStatus;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Enums\RuleVersionStatus;
use App\Models\Obligation;
use App\Models\ObligationGenerationRun;
use App\Models\User;
use App\Support\CalculatorRegistry;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CommitGeneratedObligation
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CalculatorRegistry $calculatorRegistry,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, ObligationGenerationRun $preview): Obligation
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($preview->firm_id !== $firmId) {
            throw new AuthorizationException('The generation preview does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);

        return DB::transaction(function () use ($actor, $preview): Obligation {
            $lockedPreview = ObligationGenerationRun::query()
                ->with(['ruleVersion.template'])
                ->lockForUpdate()
                ->findOrFail($preview->id);
            if ($lockedPreview->status !== GenerationRunStatus::Preview) {
                throw ValidationException::withMessages(['preview' => 'Only a preview run can be committed.']);
            }
            if ($lockedPreview->ruleVersion->status !== RuleVersionStatus::Published) {
                throw ValidationException::withMessages(['ruleVersion' => 'The preview rule is no longer published. Create a new preview.']);
            }

            $calculator = $this->calculatorRegistry->get($lockedPreview->ruleVersion->calculator_key);
            $parameters = $calculator->validateParameters($lockedPreview->parameter_snapshot);
            $result = $calculator->calculate(
                ['statutory_due_date' => $lockedPreview->input_snapshot['statutory_due_date'] ?? null],
                $parameters,
            );
            if (
                $result->statutoryDueDate !== $lockedPreview->statutory_due_date->toDateString()
                || $result->explanation !== $lockedPreview->calculation_explanation
            ) {
                throw ValidationException::withMessages(['preview' => 'The calculator result no longer matches the immutable preview.']);
            }

            $committedRun = ObligationGenerationRun::query()->firstOrCreate(
                [
                    'status' => GenerationRunStatus::Committed,
                    'deterministic_key' => $lockedPreview->deterministic_key,
                ],
                [
                    'preview_run_id' => $lockedPreview->id,
                    'client_id' => $lockedPreview->client_id,
                    'client_service_enrollment_id' => $lockedPreview->client_service_enrollment_id,
                    'tax_period_id' => $lockedPreview->tax_period_id,
                    'obligation_rule_version_id' => $lockedPreview->obligation_rule_version_id,
                    'input_snapshot' => $lockedPreview->input_snapshot,
                    'parameter_snapshot' => $lockedPreview->parameter_snapshot,
                    'result_snapshot' => $lockedPreview->result_snapshot,
                    'statutory_due_date' => $lockedPreview->statutory_due_date,
                    'internal_target_date' => $lockedPreview->internal_target_date,
                    'calculation_explanation' => $lockedPreview->calculation_explanation,
                    'created_by' => $actor->id,
                ],
            );

            $obligation = Obligation::query()->firstOrCreate(
                ['generation_key' => $lockedPreview->deterministic_key],
                [
                    'client_id' => $lockedPreview->client_id,
                    'client_service_enrollment_id' => $lockedPreview->client_service_enrollment_id,
                    'tax_period_id' => $lockedPreview->tax_period_id,
                    'obligation_rule_version_id' => $lockedPreview->obligation_rule_version_id,
                    'generation_run_id' => $committedRun->id,
                    'calculation_input_snapshot' => $lockedPreview->input_snapshot,
                    'calculation_parameter_snapshot' => $lockedPreview->parameter_snapshot,
                    'calculation_result_snapshot' => $lockedPreview->result_snapshot,
                    'calculation_explanation' => $lockedPreview->calculation_explanation,
                    'obligation_type' => $lockedPreview->ruleVersion->template->obligation_type,
                    'period_label' => $lockedPreview->input_snapshot['period_label'],
                    'statutory_due_date' => $lockedPreview->statutory_due_date,
                    'internal_target_date' => $lockedPreview->internal_target_date,
                    'origin' => ObligationOrigin::GovernedRule,
                    'status' => ObligationStatus::Open,
                    'source_reference' => $lockedPreview->ruleVersion->official_source_url,
                    'last_verified_on' => $lockedPreview->ruleVersion->source_last_verified_on,
                    'verified_by' => $lockedPreview->ruleVersion->verified_by,
                    'created_by' => $actor->id,
                ],
            );

            if ($obligation->wasRecentlyCreated) {
                $this->recordAudit->handle(
                    action: 'obligation_generation.committed',
                    actor: $actor,
                    auditable: $obligation,
                    after: [
                        'generation_run_id' => $committedRun->id,
                        'rule_version_id' => $lockedPreview->obligation_rule_version_id,
                        'deterministic_key' => $lockedPreview->deterministic_key,
                        'statutory_due_date' => $lockedPreview->statutory_due_date->toDateString(),
                    ],
                );
            }

            return $obligation->refresh();
        }, 3);
    }
}
