<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\DuplicateSignalType;
use App\Enums\Feature;
use App\Models\DuplicateCandidate;
use App\Models\DuplicateCandidateSignal;
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

final readonly class RecordDuplicateCandidateSignal
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    /** @return array{candidate: DuplicateCandidate, signal: DuplicateCandidateSignal} */
    public function handle(
        User $actor,
        PartyRecord $firstParty,
        PartyRecord $secondParty,
        mixed $signalType,
        mixed $firstNormalizedValue,
        mixed $secondNormalizedValue,
        mixed $normalizerVersion,
        mixed $contributionExplanation,
    ): array {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($firstParty->firm_id !== $firmId || $secondParty->firm_id !== $firmId) {
            throw new AuthorizationException('Both parties must belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('update', $firstParty);
        Gate::forUser($actor)->authorize('update', $secondParty);
        if ($firstParty->id === $secondParty->id) {
            throw ValidationException::withMessages(['secondParty' => 'Select two different party records.']);
        }
        if ($firstParty->client_id !== $secondParty->client_id) {
            throw ValidationException::withMessages(['secondParty' => 'Duplicate review is currently limited to parties of the same client.']);
        }
        /** @var array{signal_type: string, first_value: string, second_value: string, normalizer_version: string, explanation: string} $validated */
        $validated = Validator::make([
            'signal_type' => $signalType,
            'first_value' => $firstNormalizedValue,
            'second_value' => $secondNormalizedValue,
            'normalizer_version' => $normalizerVersion,
            'explanation' => $contributionExplanation,
        ], [
            'signal_type' => ['required', Rule::enum(DuplicateSignalType::class)],
            'first_value' => ['required', 'string', 'max:1000'],
            'second_value' => ['required', 'string', 'max:1000'],
            'normalizer_version' => ['required', 'string', 'max:80'],
            'explanation' => ['required', 'string', 'max:500'],
        ])->validate();

        [$firstId, $secondId] = collect([$firstParty->id, $secondParty->id])->sort()->values()->all();
        $candidateKey = hash('sha256', implode('|', [$firmId, $firstId, $secondId]));

        return DB::transaction(function () use ($actor, $validated, $candidateKey, $firstId, $secondId): array {
            $candidate = DuplicateCandidate::query()->firstOrCreate(
                ['candidate_key' => $candidateKey],
                ['first_party_record_id' => $firstId, 'second_party_record_id' => $secondId, 'recorded_by' => $actor->id, 'recorded_at' => now()],
            );
            if ($candidate->decision()->exists()) {
                throw ValidationException::withMessages(['candidate' => 'A decided duplicate candidate cannot accept new signals.']);
            }
            $signal = DuplicateCandidateSignal::query()->firstOrCreate(
                ['duplicate_candidate_id' => $candidate->id, 'signal_type' => $validated['signal_type']],
                [
                    'first_normalized_value' => trim($validated['first_value']),
                    'second_normalized_value' => trim($validated['second_value']),
                    'normalizer_version' => trim($validated['normalizer_version']),
                    'contribution_explanation' => trim($validated['explanation']),
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ],
            );
            if ($signal->wasRecentlyCreated) {
                $this->audit->handle('duplicate_candidate.signal_recorded', $actor, $signal, [], [
                    'duplicate_candidate_id' => $candidate->id,
                    'signal_type' => $validated['signal_type'],
                    'normalizer_version' => $validated['normalizer_version'],
                ]);
            }

            return ['candidate' => $candidate->refresh(), 'signal' => $signal->refresh()];
        }, 3);
    }
}
