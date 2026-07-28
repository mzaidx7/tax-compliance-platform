<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\PartyCorrectionProposal;
use App\Models\PartyFieldVersion;
use App\Models\PartyRecord;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ProposePartyFieldCorrection
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(
        User $actor,
        PartyRecord $party,
        PartyFieldVersion $current,
        mixed $proposedValue,
        mixed $evidenceNote,
    ): PartyCorrectionProposal {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($party->firm_id !== $firmId || $current->firm_id !== $firmId || $current->party_record_id !== $party->id) {
            throw new AuthorizationException('The correction inputs must belong to the active firm and party.');
        }
        Gate::forUser($actor)->authorize('update', $party);
        /** @var array{value: string, evidence: string} $validated */
        $validated = Validator::make(['value' => $proposedValue, 'evidence' => $evidenceNote], [
            'value' => ['required', 'string', 'max:4000'], 'evidence' => ['required', 'string', 'max:1000'],
        ])->validate();
        if (trim($validated['value']) === $current->value) {
            throw ValidationException::withMessages(['proposedValue' => 'The proposed value must differ from the current value.']);
        }
        $latest = $party->currentField($current->field_key->value);
        if ($latest?->id !== $current->id) {
            throw ValidationException::withMessages(['currentFieldVersion' => 'The selected field version is no longer current.']);
        }

        return DB::transaction(function () use ($actor, $party, $current, $validated): PartyCorrectionProposal {
            $proposal = PartyCorrectionProposal::query()->create([
                'party_record_id' => $party->id, 'current_party_field_version_id' => $current->id,
                'proposed_value' => trim($validated['value']), 'evidence_note' => trim($validated['evidence']),
                'proposed_by' => $actor->id, 'proposed_at' => now(),
            ]);
            $this->audit->handle('party_correction.proposed', $actor, $proposal, [], [
                'party_record_id' => $party->id, 'field_key' => $current->field_key->value,
                'current_field_version_id' => $current->id,
            ]);

            return $proposal->refresh();
        }, 3);
    }
}
