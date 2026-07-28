<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\DuplicateDecisionOutcome;
use App\Enums\Feature;
use App\Models\DuplicateCandidate;
use App\Models\DuplicateCandidateDecision;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class DecideDuplicateCandidate
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(User $actor, DuplicateCandidate $candidate, mixed $outcome, mixed $reason): DuplicateCandidateDecision
    {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($candidate->firm_id !== $firmId) {
            throw new AuthorizationException('The candidate does not belong to the active firm.');
        }
        $candidate->loadMissing('firstParty');
        Gate::forUser($actor)->authorize('approveCorrection', $candidate->firstParty);
        /** @var array{outcome: string, reason: string} $validated */
        $validated = Validator::make(compact('outcome', 'reason'), [
            'outcome' => ['required', Rule::enum(DuplicateDecisionOutcome::class)],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $candidate, $validated): DuplicateCandidateDecision {
            $locked = DuplicateCandidate::query()->with(['decision', 'signals'])->lockForUpdate()->findOrFail($candidate->id);
            if ($locked->decision !== null) {
                throw ValidationException::withMessages(['candidate' => 'This duplicate candidate already has a decision.']);
            }
            if ($locked->recorded_by === $actor->id) {
                throw ValidationException::withMessages(['decider' => 'The decision maker must differ from the candidate recorder.']);
            }
            if ($locked->signals->isEmpty()) {
                throw ValidationException::withMessages(['candidate' => 'At least one retained signal is required.']);
            }
            $decision = DuplicateCandidateDecision::query()->create([
                'duplicate_candidate_id' => $locked->id,
                'outcome' => $validated['outcome'],
                'reason' => trim($validated['reason']),
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);
            $this->audit->handle('duplicate_candidate.decided', $actor, $decision, [], [
                'duplicate_candidate_id' => $locked->id,
                'outcome' => $validated['outcome'],
            ]);

            return $decision->refresh();
        }, 3);
    }
}
