<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\PartyIssue;
use App\Models\PartyIssueResolution;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class ResolvePartyIssue
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(User $actor, PartyIssue $issue, mixed $outcome, mixed $reason): PartyIssueResolution
    {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($issue->firm_id !== $firmId) {
            throw new AuthorizationException('The issue does not belong to the active firm.');
        }
        $issue->loadMissing('party');
        Gate::forUser($actor)->authorize('approveCorrection', $issue->party);
        /** @var array{outcome: string, reason: string} $validated */
        $validated = Validator::make(compact('outcome', 'reason'), [
            'outcome' => ['required', Rule::in(['resolved', 'not_applicable'])], 'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $issue, $validated): PartyIssueResolution {
            $locked = PartyIssue::query()->with('resolution')->lockForUpdate()->findOrFail($issue->id);
            if ($locked->resolution !== null) {
                throw ValidationException::withMessages(['issue' => 'This issue already has a resolution decision.']);
            }
            if ($locked->recorded_by === $actor->id) {
                throw ValidationException::withMessages(['resolver' => 'The resolver must differ from the issue recorder.']);
            }
            $resolution = PartyIssueResolution::query()->create([
                'party_issue_id' => $locked->id, 'outcome' => $validated['outcome'],
                'reason' => trim($validated['reason']), 'resolved_by' => $actor->id, 'resolved_at' => now(),
            ]);
            $this->audit->handle('party_issue.resolved', $actor, $resolution, [], [
                'party_issue_id' => $locked->id, 'outcome' => $validated['outcome'],
            ]);

            return $resolution->refresh();
        }, 3);
    }
}
