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
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only audit register.
 *
 * This component never writes. It has no create, edit, delete or export path,
 * and it relies on the tenant global scope so one firm can never read another
 * firm's retained evidence.
 */
#[Title('Audit register')]
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

    public function exportRegister(ExportAuditRegister $exportAuditRegister): void
    {
        $artifact = $exportAuditRegister->handle($this->currentUser(), $this->filters());

        Flux::toast(
            variant: 'success',
            text: "Exported {$artifact->rowCount} retained records. The download was recorded separately.",
        );
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
