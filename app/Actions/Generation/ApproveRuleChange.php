<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Actions\Audit\RecordAudit;
use App\Actions\Compliance\DisposeObligation;
use App\Enums\Feature;
use App\Enums\ObligationStatus;
use App\Models\RuleChangeProposal;
use App\Models\RuleChangeProposalDecision;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ApproveRuleChange
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private CommitGeneratedObligation $commitGeneratedObligation,
        private DisposeObligation $disposeObligation,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, RuleChangeProposal $proposal, mixed $reason): RuleChangeProposalDecision
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($proposal->firm_id !== $firmId) {
            throw new AuthorizationException('The proposal does not belong to the active firm.');
        }

        $proposal->loadMissing('originalObligation');
        Gate::forUser($actor)->authorize('update', $proposal->originalObligation);
        /** @var array{reason: string} $validated */
        $validated = Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($actor, $proposal, $validated): RuleChangeProposalDecision {
            $locked = RuleChangeProposal::query()
                ->with(['decision', 'originalObligation', 'previewRun.ruleVersion'])
                ->lockForUpdate()
                ->findOrFail($proposal->id);

            if ($locked->decision !== null) {
                throw ValidationException::withMessages(['proposal' => 'This proposal already has a decision.']);
            }
            if ($locked->originalObligation->status !== ObligationStatus::Open) {
                throw ValidationException::withMessages(['proposal' => 'The original obligation is no longer open.']);
            }

            $replacement = $this->commitGeneratedObligation->handle($actor, $locked->previewRun);
            $this->disposeObligation->handle($actor, $locked->originalObligation, [
                'status' => ObligationStatus::Superseded->value,
                'replacementObligationId' => $replacement->id,
                'reason' => trim($validated['reason']),
            ]);

            $decision = RuleChangeProposalDecision::query()->create([
                'rule_change_proposal_id' => $locked->id,
                'decision' => 'approved',
                'replacement_obligation_id' => $replacement->id,
                'reason' => trim($validated['reason']),
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            $this->recordAudit->handle(
                action: 'rule_change.approved',
                actor: $actor,
                auditable: $locked,
                after: [
                    'decision_id' => $decision->id,
                    'replacement_obligation_id' => $replacement->id,
                ],
                reason: trim($validated['reason']),
            );

            return $decision->refresh();
        }, 3);
    }
}
