<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\PartyCorrectionDecision;
use App\Models\PartyCorrectionProposal;
use App\Models\PartyFieldVersion;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class DecidePartyFieldCorrection
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(User $actor, PartyCorrectionProposal $proposal, mixed $decision, mixed $reason): PartyCorrectionDecision
    {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($proposal->firm_id !== $firmId) {
            throw new AuthorizationException('The proposal does not belong to the active firm.');
        }
        $proposal->loadMissing('party');
        Gate::forUser($actor)->authorize('approveCorrection', $proposal->party);
        /** @var array{decision: string, reason: string} $validated */
        $validated = Validator::make(compact('decision', 'reason'), [
            'decision' => ['required', Rule::in(['approved', 'rejected'])], 'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $proposal, $validated): PartyCorrectionDecision {
            $locked = PartyCorrectionProposal::query()
                ->with(['decision', 'party', 'currentFieldVersion'])
                ->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->decision !== null) {
                throw ValidationException::withMessages(['proposal' => 'This correction already has a decision.']);
            }
            if ($locked->proposed_by === $actor->id) {
                throw ValidationException::withMessages(['approver' => 'The decision maker must differ from the proposer.']);
            }
            $newField = null;
            if ($validated['decision'] === 'approved') {
                $current = $locked->party->currentField($locked->currentFieldVersion->field_key->value);
                if ($current?->id !== $locked->current_party_field_version_id) {
                    throw ValidationException::withMessages(['proposal' => 'The proposal is stale because the current field changed.']);
                }
                $newField = PartyFieldVersion::query()->create([
                    'party_record_id' => $locked->party_record_id,
                    'field_key' => $locked->currentFieldVersion->field_key,
                    'value' => $locked->proposed_value,
                    'verification_state' => $locked->currentFieldVersion->verification_state,
                    'source_kind' => 'approved_correction',
                    'source_reference' => $locked->id,
                    'supersedes_party_field_version_id' => $locked->current_party_field_version_id,
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ]);
            }
            $decision = PartyCorrectionDecision::query()->create([
                'party_correction_proposal_id' => $locked->id, 'decision' => $validated['decision'],
                'new_party_field_version_id' => $newField?->id, 'reason' => trim($validated['reason']),
                'decided_by' => $actor->id, 'decided_at' => now(),
            ]);
            $this->audit->handle('party_correction.decided', $actor, $decision, [], [
                'proposal_id' => $locked->id, 'decision' => $validated['decision'],
                'new_field_version_id' => $newField?->id,
            ]);

            return $decision->refresh();
        }, 3);
    }
}
