<?php

declare(strict_types=1);

namespace App\Livewire\Audit;

use App\Actions\Audit\ExportAuditRegister;
use App\Data\AuditRegisterFilters;
use App\Enums\Feature;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only audit register.
 *
 * This component never changes retained audit records. It relies on the tenant
 * global scope so one firm can never read another firm's retained evidence.
 */
#[Title('Activity history')]
final class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $action = '';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(FeatureFlags $featureFlags, FirmContext $firmContext): void
    {
        abort_unless(
            $featureFlags->enabled(Feature::AuditViewer, $firmContext->firmId()),
            404,
        );

        Gate::authorize('viewAny', AuditLog::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'action', 'fromDate', 'toDate');
        $this->resetPage();
        unset($this->records);
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return $this->filters()
            ->apply(AuditLog::query())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * The register and its export must always agree on what matches.
     */
    public function filters(): AuditRegisterFilters
    {
        return AuditRegisterFilters::fromStrings(
            $this->search,
            $this->action,
            $this->fromDate,
            $this->toDate,
        );
    }

    public function exportRegister(ExportAuditRegister $exportAuditRegister): mixed
    {
        $artifact = $exportAuditRegister->handle($this->currentUser(), $this->filters());

        return redirect()->route(
            'exports.download',
            ['exportAuditLog' => $artifact->auditLogId],
        );
    }

    public function actionLabel(string $action): string
    {
        return match ($action) {
            'checklist.version_published' => __('Task checklist updated'),
            'workflow.version_published' => __('Task workflow updated'),
            'client.created' => __('Client created'),
            'client.updated' => __('Client details updated'),
            'client.status_changed' => __('Client status changed'),
            'client.contact_added' => __('Client contact added'),
            'client.service_enrolled' => __('Client service added'),
            'client.service_status_changed' => __('Client service status changed'),
            'client.tax_period_added' => __('Tax period added'),
            'client.tax_registration_added' => __('Tax registration added'),
            'client.csv_import_committed', 'client.imported' => __('Clients imported'),
            'client.compliance_schedule_generated' => __('Client deadlines generated'),
            'client.reminder_requested' => __('Client reminder requested'),
            'client.reminder_approved' => __('Client reminder approved'),
            'client.reminder_sent' => __('Client reminder sent'),
            'client.reminder_preferences_changed' => __('Client reminder settings changed'),
            'obligation.created', 'obligation.manual_created' => __('Due date created'),
            'obligation.updated', 'obligation.deadline_overridden' => __('Due date updated'),
            'obligation.disposed' => __('Due date closed or replaced'),
            'work_item.transitioned', 'work_item.status_transitioned' => __('Client task status changed'),
            'work_item.assigned', 'work_item.created_and_assigned', 'work_item.reassigned' => __('Client task assigned'),
            'work_item.review_decided' => __('Client task review decided'),
            'work_item.risk_status_changed' => __('Client task attention level changed'),
            'work_item.reopened' => __('Client task reopened'),
            'work_item.workflow_version_migrated' => __('Client task workflow updated'),
            'checklist.item_completed' => __('Checklist item completed'),
            'filing.transitioned', 'filing_record.status_transitioned' => __('Filing status changed'),
            'filing_record.created' => __('Filing record created'),
            'payment.transitioned', 'payment_record.status_transitioned' => __('Payment status changed'),
            'payment_record.created' => __('Payment record created'),
            'tax_record.created' => __('Tax amount recorded'),
            'tax_record.amended' => __('Tax amount updated'),
            'client.document_metadata_recorded' => __('Document details recorded'),
            'document_evidence.uploaded' => __('Supporting document uploaded'),
            'document_evidence.downloaded' => __('Supporting document downloaded'),
            'firm.invitation.created' => __('Team invitation sent'),
            'firm.invitation.resent' => __('Team invitation resent'),
            'firm.invitation.revoked' => __('Team invitation revoked'),
            'firm.invitation.accepted' => __('Team invitation accepted'),
            'firm.membership.role_changed' => __('Team member role changed'),
            'firm.membership.reactivated' => __('Team member reactivated'),
            'firm.membership.suspended' => __('Team member suspended'),
            'firm.membership.revoked' => __('Team member access revoked'),
            'firm.context.switched' => __('Firm workspace changed'),
            'firm.export.created' => __('Export prepared'),
            'firm.export.downloaded' => __('Export downloaded'),
            'audit_register.exported' => __('Activity history exported'),
            default => Str::of($action)
                ->replace(['.', '_'], ' ')
                ->headline()
                ->toString(),
        };
    }

    public function recordTypeLabel(?string $type): string
    {
        return match (class_basename((string) $type)) {
            'AuditLog' => __('Activity record'),
            'ChecklistVersion' => __('Task checklist'),
            'WorkflowDefinition', 'WorkflowDefinitionVersion' => __('Task workflow'),
            'Client' => __('Client'),
            'ClientDocument' => __('Client document'),
            'ClientPerson' => __('Client contact or authorised person'),
            'Obligation' => __('Tax or compliance due date'),
            'WorkItem' => __('Client task'),
            'FilingRecord' => __('Tax return filing'),
            'PaymentRecord' => __('Tax payment'),
            'TaxRecord' => __('Tax amount record'),
            'FirmMembership' => __('Team member'),
            default => Str::of(class_basename((string) $type))
                ->headline()
                ->toString(),
        };
    }

    public function fieldLabel(string $field): string
    {
        return Str::of($field)->replace('_', ' ')->headline()->toString();
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('An authenticated member is required.');
        }

        return $user;
    }

    /**
     * Distinct recorded actions for this firm, used to populate the filter.
     *
     * @return list<string>
     */
    #[Computed]
    public function actions(): array
    {
        /** @var list<string> $actions */
        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return $actions;
    }

    /**
     * Actor display names for the current page only.
     *
     * @return Collection<int|string, string>
     */
    #[Computed]
    public function actorNames(): Collection
    {
        $actorIds = collect($this->records()->items())
            ->filter(static fn (AuditLog $record): bool => $record->actor_type === (new User)->getMorphClass())
            ->pluck('actor_id')
            ->filter()
            ->unique();

        if ($actorIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $actorIds->all())
            ->pluck('name', 'id');
    }

    public function render(): View
    {
        return view('livewire.audit.index');
    }
}
