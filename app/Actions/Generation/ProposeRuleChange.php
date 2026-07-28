<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\ObligationOrigin;
use App\Enums\ObligationStatus;
use App\Enums\RuleVersionStatus;
use App\Models\Obligation;
use App\Models\ObligationRuleVersion;
use App\Models\RuleChangeProposal;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ProposeRuleChange
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private PreviewGeneratedObligation $previewGeneratedObligation,
        private RecordAudit $recordAudit,
    ) {}

    /**
     * @param array{
     *   statutoryDueDate: mixed,
     *   internalTargetDate?: mixed,
     *   reason: mixed
     * } $input
     */
    public function handle(
        User $actor,
        Obligation $original,
        ObligationRuleVersion $proposedRuleVersion,
        array $input,
    ): RuleChangeProposal {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($original->firm_id !== $firmId || $proposedRuleVersion->firm_id !== $firmId) {
            throw new AuthorizationException('The rule proposal inputs must belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $original);

        /** @var array{statutoryDueDate: string, internalTargetDate: string|null, reason: string} $validated */
        $validated = Validator::make(
            [...$input, 'internalTargetDate' => $input['internalTargetDate'] ?? null],
            [
                'statutoryDueDate' => ['required', 'date_format:Y-m-d', 'different:originalDate'],
                'internalTargetDate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:statutoryDueDate'],
                'reason' => ['required', 'string', 'max:500'],
                'originalDate' => ['nullable'],
            ],
        )->setData([...$input, 'internalTargetDate' => $input['internalTargetDate'] ?? null, 'originalDate' => $original->statutory_due_date->toDateString()])
            ->validate();

        $original->loadMissing(['client', 'serviceEnrollment', 'taxPeriod', 'ruleVersion']);
        if (
            $original->origin !== ObligationOrigin::GovernedRule
            || $original->status !== ObligationStatus::Open
            || $original->ruleVersion === null
            || $original->serviceEnrollment === null
            || $original->calculation_input_snapshot === null
        ) {
            throw ValidationException::withMessages(['original' => 'Only an open governed obligation with complete generation snapshots can be proposed for rule change.']);
        }
        if (
            $proposedRuleVersion->status !== RuleVersionStatus::Published
            || $proposedRuleVersion->obligation_rule_template_id !== $original->ruleVersion->obligation_rule_template_id
            || $proposedRuleVersion->version <= $original->ruleVersion->version
        ) {
            throw ValidationException::withMessages(['ruleVersion' => 'Select a later published version of the same rule template.']);
        }

        $preview = $this->previewGeneratedObligation->handle(
            $actor,
            $original->client,
            $original->serviceEnrollment,
            $original->taxPeriod,
            $proposedRuleVersion,
            [
                'applicabilityDate' => $original->calculation_input_snapshot['applicability_date'] ?? null,
                'periodLabel' => $original->calculation_input_snapshot['period_label'] ?? null,
                'statutoryDueDate' => $validated['statutoryDueDate'],
                'internalTargetDate' => $validated['internalTargetDate'],
            ],
        );

        return DB::transaction(function () use ($actor, $original, $proposedRuleVersion, $validated, $preview): RuleChangeProposal {
            $proposal = RuleChangeProposal::query()->firstOrCreate(
                [
                    'original_obligation_id' => $original->id,
                    'preview_run_id' => $preview->id,
                ],
                [
                    'proposed_rule_version_id' => $proposedRuleVersion->id,
                    'original_statutory_due_date' => $original->statutory_due_date,
                    'proposed_statutory_due_date' => $preview->statutory_due_date,
                    'reason' => trim($validated['reason']),
                    'proposed_by' => $actor->id,
                    'proposed_at' => now(),
                ],
            );

            if ($proposal->wasRecentlyCreated) {
                $this->recordAudit->handle(
                    action: 'rule_change.proposed',
                    actor: $actor,
                    auditable: $proposal,
                    after: [
                        'original_obligation_id' => $original->id,
                        'original_statutory_due_date' => $original->statutory_due_date->toDateString(),
                        'proposed_statutory_due_date' => $preview->statutory_due_date->toDateString(),
                        'proposed_rule_version_id' => $proposedRuleVersion->id,
                    ],
                    reason: trim($validated['reason']),
                );
            }

            return $proposal->refresh();
        }, 3);
    }
}
