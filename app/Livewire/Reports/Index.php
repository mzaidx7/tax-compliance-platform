<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Actions\Reports\BuildOperationalReport;
use App\Enums\OperationalReportType;
use App\Enums\Permission;
use App\Models\User;
use App\Tenancy\FirmContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reports')]
final class Index extends Component
{
    public string $reportType = 'monthly_schedule';

    public string $month = '';

    public function mount(FirmContext $context): void
    {
        $membership = $context->membership();
        abort_unless($membership?->user_id === $this->user()->id && $membership->hasPermission(Permission::ViewReports), 403);
        $this->month = today()->format('Y-m');
    }

    public function updated(): void
    {
        $this->validate([
            'reportType' => ['required', Rule::enum(OperationalReportType::class)],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        unset($this->report);
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|bool|null>>, truncated: bool}
     */
    #[Computed]
    public function report(): array
    {
        return app(BuildOperationalReport::class)->preview(
            $this->user(),
            OperationalReportType::from($this->reportType),
            $this->month,
        );
    }

    /** @return list<OperationalReportType> */
    public function reportTypes(): array
    {
        return OperationalReportType::cases();
    }

    public function exportReport(BuildOperationalReport $report): mixed
    {
        $artifact = $report->export(
            $this->user(),
            OperationalReportType::from($this->reportType),
            $this->month,
        );

        return redirect()->route('exports.download', ['exportAuditLog' => $artifact->auditLogId]);
    }

    public function render(): View
    {
        return view('livewire.reports.index');
    }

    private function user(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw new AuthorizationException('An authenticated member is required.');
        }

        return $user;
    }
}
