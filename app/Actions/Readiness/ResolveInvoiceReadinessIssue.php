<?php

declare(strict_types=1);

namespace App\Actions\Readiness;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\InvoiceIssueResolution;
use App\Models\InvoiceReadinessIssue;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class ResolveInvoiceReadinessIssue
{
    public function __construct(private FirmContext $context, private FeatureFlags $flags, private RecordAudit $audit) {}

    public function handle(User $actor, InvoiceReadinessIssue $issue, mixed $outcome, mixed $reason): InvoiceIssueResolution
    {
        $firmId = $this->context->firmId();
        if (! $this->flags->enabled(Feature::EInvoicingReadiness, $firmId)) {
            throw new AuthorizationException('E-invoicing readiness is not enabled.');
        }
        if ($issue->firm_id !== $firmId) {
            throw new AuthorizationException('The issue does not belong to the active firm.');
        }
        $issue->loadMissing('sample');
        Gate::forUser($actor)->authorize('resolveIssue', $issue->sample);
        /** @var array{outcome: string, reason: string} $validated */
        $validated = Validator::make(compact('outcome', 'reason'), [
            'outcome' => ['required', Rule::in(['resolved', 'not_applicable'])],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($actor, $issue, $validated): InvoiceIssueResolution {
            $locked = InvoiceReadinessIssue::query()->with('resolution')->lockForUpdate()->findOrFail($issue->id);
            if ($locked->resolution !== null) {
                throw ValidationException::withMessages(['issue' => 'This invoice issue already has a resolution decision.']);
            }
            if ($locked->recorded_by === $actor->id) {
                throw ValidationException::withMessages(['resolver' => 'The resolver must differ from the issue recorder.']);
            }
            $resolution = InvoiceIssueResolution::query()->create([
                'invoice_readiness_issue_id' => $locked->id,
                'outcome' => $validated['outcome'],
                'reason' => trim($validated['reason']),
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
            $this->audit->handle('invoice_readiness_issue.resolved', $actor, $resolution, [], [
                'invoice_readiness_issue_id' => $locked->id,
                'outcome' => $validated['outcome'],
            ]);

            return $resolution->refresh();
        }, 3);
    }
}
