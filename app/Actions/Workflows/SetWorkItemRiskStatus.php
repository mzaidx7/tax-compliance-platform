<?php

declare(strict_types=1);

namespace App\Actions\Workflows;

use App\Actions\Audit\RecordAudit;
use App\Actions\Notifications\DispatchFirmNotification;
use App\Enums\Feature;
use App\Enums\RiskLevel;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemRiskChange;
use App\Notifications\WorkItemHighRiskNotification;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Set the risk status of a work item.
 *
 * Risk status is a stored assessment field on the work item, independent of
 * work, filing, payment and tax state. This action never infers risk
 * automatically and never changes any other stored dimension.
 */
final readonly class SetWorkItemRiskStatus
{
    public function __construct(
        private FirmContext $firmContext,
        private FeatureFlags $featureFlags,
        private RecordAudit $recordAudit,
        private DispatchFirmNotification $dispatchFirmNotification,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(
        User $actor,
        WorkItem $workItem,
        RiskLevel $riskLevel,
        string $reason,
    ): WorkItemRiskChange {
        $firmId = $this->firmContext->firm()->id;

        if (! $this->featureFlags->enabled(Feature::ComplianceOperations, $firmId)) {
            throw new AuthorizationException('Compliance operations are not enabled for this firm.');
        }

        Gate::forUser($actor)->authorize('update', $workItem);

        /** @var array{riskLevel: string, reason: string} $validated */
        $validated = Validator::make(
            ['riskLevel' => $riskLevel->value, 'reason' => $reason],
            [
                'riskLevel' => ['required', Rule::enum(RiskLevel::class)],
                'reason' => ['required', 'string', 'max:500'],
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $workItem, $riskLevel, $validated): WorkItemRiskChange {
            $lockedWorkItem = WorkItem::query()->lockForUpdate()->findOrFail($workItem->id);
            $previousRisk = $lockedWorkItem->risk_status;

            if ($previousRisk === $riskLevel) {
                throw ValidationException::withMessages([
                    'riskLevel' => "Risk is already {$riskLevel->label()}.",
                ]);
            }

            $change = WorkItemRiskChange::query()->create([
                'work_item_id' => $lockedWorkItem->id,
                'previous_risk_status' => $previousRisk,
                'new_risk_status' => $riskLevel,
                'changed_by' => $actor->id,
                'reason' => trim($validated['reason']),
                'changed_at' => now('UTC'),
            ]);

            $lockedWorkItem->update(['risk_status' => $riskLevel]);

            $this->recordAudit->handle(
                action: 'work_item.risk_status_changed',
                actor: $actor,
                auditable: $lockedWorkItem,
                before: ['risk_status' => $previousRisk->value],
                after: ['risk_status' => $riskLevel->value],
                reason: trim($validated['reason']),
            );

            $this->notifyResponsibleManager($actor, $lockedWorkItem, $riskLevel, $change->id);

            return $change->refresh();
        }, 3);
    }

    /**
     * Notify the accountable manager that work is now recorded at high risk.
     *
     * Only an explicit escalation to high notifies. When there is no active
     * responsible manager there is nobody valid to address, so the change is
     * still recorded and the notification is skipped rather than guessed at.
     */
    private function notifyResponsibleManager(
        User $actor,
        WorkItem $workItem,
        RiskLevel $riskLevel,
        string $riskChangeId,
    ): void {
        if ($riskLevel !== RiskLevel::High) {
            return;
        }

        $recipient = $workItem->responsibleManagerUser();

        if (! $recipient instanceof User) {
            return;
        }

        $this->dispatchFirmNotification->handle(
            recipient: $recipient,
            notification: new WorkItemHighRiskNotification(
                $workItem->firm_id,
                (int) $recipient->getKey(),
                $workItem->id,
            ),
            idempotencyKey: "work-item-high-risk:{$riskChangeId}",
            actor: $actor,
        );
    }
}
