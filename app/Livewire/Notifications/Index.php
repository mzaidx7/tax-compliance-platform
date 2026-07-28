<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Actions\Notifications\GenerateManagerOperationalSummary;
use App\Actions\Notifications\MarkNotificationRead;
use App\Enums\FirmMembershipStatus;
use App\Enums\Permission;
use App\Models\FirmMembership;
use App\Models\NotificationRequest;
use App\Models\User;
use App\Tenancy\FirmContext;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
final class Index extends Component
{
    use WithPagination;

    public string $managerMembershipId = '';

    public function mount(FirmContext $context): void
    {
        abort_unless($context->membership()?->user_id === $this->user()->id, 403);
    }

    public function markRead(string $requestId, MarkNotificationRead $action): void
    {
        $request = NotificationRequest::query()
            ->where('recipient_user_id', $this->user()->id)
            ->findOrFail($requestId);
        $action->handle($this->user(), $request);
        unset($this->requests);
    }

    public function generateManagerSummary(GenerateManagerOperationalSummary $action): void
    {
        $action->handle(
            $this->user(),
            FirmMembership::query()->where('user_id', '>', 0)->findOrFail($this->managerMembershipId),
        );
        Flux::toast(variant: 'success', text: __('Manager summary request retained.'));
        unset($this->requests);
    }

    /** @return LengthAwarePaginator<int, NotificationRequest> */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return NotificationRequest::query()
            ->with(['attempts' => static fn ($query) => $query->orderByDesc('attempt_number'), 'readReceipt'])
            ->where('recipient_user_id', $this->user()->id)
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /** @return Collection<int, FirmMembership> */
    #[Computed]
    public function managers(): Collection
    {
        return FirmMembership::query()
            ->with('user')
            ->where('status', FirmMembershipStatus::Active)
            ->get()
            ->filter(fn (FirmMembership $membership): bool => $membership->hasPermission(Permission::AssignWork))
            ->values();
    }

    public function canGenerateSummary(): bool
    {
        return app(FirmContext::class)->membership()?->hasPermission(Permission::ViewReports) ?? false;
    }

    public function templateLabel(string $key): string
    {
        return match ($key) {
            'firm_access_summary' => __('Firm access summary'),
            'work_item_high_risk' => __('Work recorded high risk'),
            'payment_overdue' => __('Payment recorded overdue'),
            'manager_operational_summary' => __('Manager operational summary'),
            default => __('Operational notice'),
        };
    }

    public function render(): View
    {
        return view('livewire.notifications.index');
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
