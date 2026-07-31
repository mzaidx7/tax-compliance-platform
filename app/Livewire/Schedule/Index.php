<?php

declare(strict_types=1);

namespace App\Livewire\Schedule;

use App\Enums\ClientStatus;
use App\Enums\Feature;
use App\Enums\ObligationStatus;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Obligation;
use App\Support\FeatureFlags;
use App\Tenancy\FirmContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Compliance calendar')]
final class Index extends Component
{
    public string $mode = 'month';

    public string $anchorDate = '';

    public string $clientId = '';

    public string $status = 'all';

    public function mount(FeatureFlags $flags, FirmContext $context): void
    {
        abort_unless($flags->enabled(Feature::ComplianceOperations, $context->firmId()), 404);
        Gate::authorize('viewAny', Obligation::class);
        $this->anchorDate = today()->toDateString();
    }

    public function previousPeriod(): void
    {
        $anchor = $this->anchor();
        $this->anchorDate = match ($this->validatedMode()) {
            'month' => $anchor->subMonthNoOverflow()->toDateString(),
            'week' => $anchor->subWeek()->toDateString(),
            default => $anchor->subDays(30)->toDateString(),
        };
        unset($this->obligations, $this->calendarEvents);
    }

    public function nextPeriod(): void
    {
        $anchor = $this->anchor();
        $this->anchorDate = match ($this->validatedMode()) {
            'month' => $anchor->addMonthNoOverflow()->toDateString(),
            'week' => $anchor->addWeek()->toDateString(),
            default => $anchor->addDays(30)->toDateString(),
        };
        unset($this->obligations, $this->calendarEvents);
    }

    public function goToToday(): void
    {
        $this->anchorDate = today()->toDateString();
        unset($this->obligations, $this->calendarEvents);
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['month', 'week', 'list'], true) ? $mode : 'month';
        unset($this->obligations, $this->calendarEvents);
    }

    public function updated(): void
    {
        $this->validate([
            'mode' => ['required', Rule::in(['month', 'week', 'list'])],
            'anchorDate' => ['required', 'date_format:Y-m-d'],
            'clientId' => ['nullable', 'string', 'max:26'],
            'status' => ['required', Rule::in(['all', ...array_column(ObligationStatus::cases(), 'value')])],
        ]);
        unset($this->obligations, $this->calendarEvents, $this->timelineEvents);
    }

    /** @return Collection<int, Client> */
    #[Computed]
    public function clients(): Collection
    {
        return Client::query()
            ->where('status', ClientStatus::Active)
            ->orderBy('legal_name')
            ->get();
    }

    /** @return Collection<int, Obligation> */
    #[Computed]
    public function obligations(): Collection
    {
        [$start, $end] = $this->range();

        return Obligation::query()
            ->with(['client', 'workItems'])
            ->when($this->clientId !== '', fn ($query) => $query->where('client_id', $this->clientId))
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->validatedStatus()))
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('effective_due_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($fallback) use ($start, $end): void {
                        $fallback->whereNull('effective_due_date')
                            ->whereBetween('statutory_due_date', [$start->toDateString(), $end->toDateString()]);
                    });
            })
            ->orderByRaw('COALESCE(effective_due_date, statutory_due_date)')
            ->orderBy('id')
            ->limit(500)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{
     *   date: string, client_code: string, title: string, detail: string, kind: string,
     *   status_label: string, status_color: string
     * }>
     */
    #[Computed]
    public function calendarEvents(): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->range();
        $events = $this->obligations()->map(static fn (Obligation $obligation): array => [
            'date' => $obligation->effectiveDueDate()->toDateString(),
            'client_code' => $obligation->client->internal_code,
            'title' => $obligation->obligation_type,
            'detail' => $obligation->period_label ?: (string) __('No period label'),
            'kind' => 'obligation',
            'status_label' => $obligation->status->label(),
            'status_color' => $obligation->status->badgeColor(),
        ]);

        $documents = ClientDocument::query()
            ->with(['client', 'person', 'documentTypeVersion'])
            ->whereNotNull('expires_on')
            ->whereDoesntHave('successor')
            ->when($this->clientId !== '', fn ($query) => $query->where('client_id', $this->clientId))
            ->whereBetween('expires_on', [$start->toDateString(), $end->toDateString()])
            ->orderBy('expires_on')
            ->limit(500)
            ->get()
            ->map(static fn (ClientDocument $document): array => [
                'date' => $document->expires_on->toDateString(),
                'client_code' => $document->client->internal_code,
                'title' => $document->documentTypeVersion->name,
                'detail' => $document->client_person_id === null
                    ? (string) __('Client document')
                    : $document->person->name,
                'kind' => 'document',
                'status_label' => $document->expires_on->isPast()
                    ? (string) __('Expired')
                    : (string) __('Expires'),
                'status_color' => $document->expires_on->isPast() ? 'red' : 'amber',
            ]);

        return $events->concat($documents)->sortBy([
            ['date', 'asc'],
            ['client_code', 'asc'],
            ['title', 'asc'],
        ])->values();
    }

    /**
     * @return list<array{date: string, label: string, detail: string, kind: string}>
     */
    #[Computed]
    public function timelineEvents(): array
    {
        if ($this->clientId === '') {
            return [];
        }

        $client = Client::query()->with([
            'statusChanges',
            'obligations.workItems.transitions',
            'obligations.filingRecord.transitions',
            'obligations.paymentRecord.transitions',
        ])->findOrFail($this->clientId);
        $events = [];

        foreach ($client->statusChanges as $change) {
            $events[] = [
                'date' => $change->changed_at->toDateTimeString(),
                'label' => __('Client status changed'),
                'detail' => __(':from to :to', ['from' => $change->previous_status->label(), 'to' => $change->new_status->label()]),
                'kind' => 'client',
            ];
        }
        foreach ($client->obligations as $obligation) {
            $events[] = [
                'date' => $obligation->created_at?->toDateTimeString() ?? $obligation->effectiveDueDate()->startOfDay()->toDateTimeString(),
                'label' => __('Obligation recorded'),
                'detail' => "{$obligation->obligation_type} / ".($obligation->period_label ?: __('No period label')),
                'kind' => 'obligation',
            ];
            foreach ($obligation->workItems as $workItem) {
                foreach ($workItem->transitions as $transition) {
                    $events[] = [
                        'date' => $transition->transitioned_at->toDateTimeString(),
                        'label' => __('Work status changed'),
                        'detail' => __(':from to :to', ['from' => $transition->from_status->label(), 'to' => $transition->to_status->label()]),
                        'kind' => 'work',
                    ];
                }
            }
            if ($obligation->filingRecord !== null) {
                foreach ($obligation->filingRecord->transitions as $transition) {
                    $events[] = [
                        'date' => $transition->transitioned_at->toDateTimeString(),
                        'label' => __('Filing status recorded'),
                        'detail' => $transition->to_status->label(),
                        'kind' => 'filing',
                    ];
                }
            }
            if ($obligation->paymentRecord !== null) {
                foreach ($obligation->paymentRecord->transitions as $transition) {
                    $events[] = [
                        'date' => $transition->transitioned_at->toDateTimeString(),
                        'label' => __('Payment status recorded'),
                        'detail' => $transition->to_status->label(),
                        'kind' => 'payment',
                    ];
                }
            }
        }

        return array_slice($this->sortTimelineEvents($events), 0, 100);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function range(): array
    {
        $anchor = $this->anchor();

        return match ($this->validatedMode()) {
            'month' => [$anchor->startOfMonth()->startOfWeek(), $anchor->endOfMonth()->endOfWeek()],
            'week' => [$anchor->startOfWeek(), $anchor->endOfWeek()],
            default => [$anchor->subDays(15), $anchor->addDays(15)],
        };
    }

    /** @return list<CarbonImmutable> */
    public function visibleDays(): array
    {
        [$start, $end] = $this->range();
        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    public function render(): View
    {
        return view('livewire.schedule.index');
    }

    private function anchor(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->anchorDate) ?: CarbonImmutable::today();
    }

    private function validatedMode(): string
    {
        return in_array($this->mode, ['month', 'week', 'list'], true) ? $this->mode : 'month';
    }

    private function validatedStatus(): string
    {
        return in_array($this->status, array_column(ObligationStatus::cases(), 'value'), true)
            ? $this->status
            : ObligationStatus::Open->value;
    }

    /**
     * @param  list<array{date: string, label: string, detail: string, kind: string}>  $events
     * @return list<array{date: string, label: string, detail: string, kind: string}>
     */
    private function sortTimelineEvents(array $events): array
    {
        usort($events, static fn (array $left, array $right): int => $right['date'] <=> $left['date']);

        return $events;
    }
}
