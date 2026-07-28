<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Enums\PartyFieldKey;
use App\Enums\PartyFieldVerificationState;
use App\Models\PartyFieldVersion;
use App\Models\PartyRecord;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class AddInitialPartyField
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(
        User $actor, PartyRecord $party, mixed $fieldKey, mixed $value,
        mixed $verificationState, mixed $sourceReference,
    ): PartyFieldVersion {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($party->firm_id !== $firmId) {
            throw new AuthorizationException('The party does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $party);
        /** @var array{field: string, value: string, state: string, source: string} $validated */
        $validated = Validator::make(['field' => $fieldKey, 'value' => $value, 'state' => $verificationState, 'source' => $sourceReference], [
            'field' => ['required', Rule::enum(PartyFieldKey::class)], 'value' => ['required', 'string', 'max:4000'],
            'state' => ['required', Rule::enum(PartyFieldVerificationState::class)], 'source' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $party, $validated): PartyFieldVersion {
            $locked = PartyRecord::query()->lockForUpdate()->findOrFail($party->id);
            if ($locked->currentField($validated['field']) !== null) {
                throw ValidationException::withMessages(['fieldKey' => 'This field already has a value. Propose a correction instead.']);
            }
            $field = PartyFieldVersion::query()->create([
                'party_record_id' => $locked->id, 'field_key' => $validated['field'], 'value' => trim($validated['value']),
                'verification_state' => $validated['state'], 'source_kind' => 'manual',
                'source_reference' => trim($validated['source']), 'supersedes_party_field_version_id' => null,
                'recorded_by' => $actor->id, 'recorded_at' => now(),
            ]);
            $this->audit->handle('party_field.initial_recorded', $actor, $field, [], [
                'party_record_id' => $locked->id, 'field_key' => $validated['field'],
                'verification_state' => $validated['state'], 'source_kind' => 'manual',
            ]);

            return $field->refresh();
        }, 3);
    }
}
