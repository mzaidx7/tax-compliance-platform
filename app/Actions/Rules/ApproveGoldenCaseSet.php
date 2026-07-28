<?php

declare(strict_types=1);

namespace App\Actions\Rules;

use App\Actions\Audit\RecordAudit;
use App\Enums\Feature;
use App\Models\CalculatorGoldenCaseSet;
use App\Models\Obligation;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ApproveGoldenCaseSet
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
    ) {}

    public function handle(User $actor, CalculatorGoldenCaseSet $set, mixed $reason): CalculatorGoldenCaseSet
    {
        $this->authorize($actor, $set);
        /** @var array{reason: string} $validated */
        $validated = Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($actor, $set, $validated): CalculatorGoldenCaseSet {
            $locked = CalculatorGoldenCaseSet::query()
                ->with(['cases.verifications'])
                ->lockForUpdate()
                ->findOrFail($set->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['caseSet' => 'Only a draft case set can be approved.']);
            }
            if ($locked->prepared_by === $actor->id) {
                throw ValidationException::withMessages(['approver' => 'The approver must be different from the set preparer.']);
            }
            if ($locked->cases->isEmpty()) {
                throw ValidationException::withMessages(['cases' => 'At least one verified golden case is required.']);
            }
            foreach ($locked->cases as $case) {
                $latest = $case->verifications->sortByDesc('verified_at')->first();
                if ($latest === null || ! $latest->passed) {
                    throw ValidationException::withMessages(['cases' => 'Every golden case must have a latest passing verification.']);
                }
            }

            $locked->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->recordAudit->handle(
                action: 'calculator_golden_case_set.approved',
                actor: $actor,
                auditable: $locked,
                before: ['status' => 'draft'],
                after: ['status' => 'approved', 'case_count' => $locked->cases->count()],
                reason: trim($validated['reason']),
            );

            return $locked->refresh();
        }, 3);
    }

    private function authorize(User $actor, CalculatorGoldenCaseSet $set): void
    {
        $firmId = $this->firmContext->firm()->id;
        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }
        if ($set->firm_id !== $firmId) {
            throw new AuthorizationException('The case set does not belong to the active firm.');
        }
        Gate::forUser($actor)->authorize('create', Obligation::class);
    }
}
