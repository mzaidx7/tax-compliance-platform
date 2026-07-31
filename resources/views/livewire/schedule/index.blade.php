{{-- THESIS: A firm schedule is a working ledger of recorded deadlines, not a decorative calendar.
     ANCHOR: The selected period and view mode stay together as one compact control rail.
     COMPOSITION: Filters lead into one continuous month, week or list register, followed by client history.
     DEPTH: Matte ink surfaces use dividers for structure and restrained gold only for today and primary focus.
     SIGNATURE: Every date cell exposes real obligation labels directly, with no hidden hover-only information. --}}
<div class="tbt-page">
    <header class="tbt-page-header">
        <p class="tbt-page-kicker">{{ app(\App\Tenancy\FirmContext::class)->firm()->name }}</p>
        <h1 class="tbt-page-title">{{ __('Compliance calendar') }}</h1>
        <p class="tbt-page-copy">{{ __('View recorded client deadlines by month, week or list. Filter the calendar by client and deadline status.') }}</p>
    </header>

    <section class="tbt-filter-panel mt-5" aria-labelledby="schedule-controls-heading">
        <h2 id="schedule-controls-heading" class="sr-only">{{ __('Calendar filters') }}</h2>
        <div class="grid gap-4 lg:grid-cols-[minmax(12rem,1fr)_minmax(10rem,0.7fr)_minmax(9rem,0.5fr)_auto] lg:items-end">
            <flux:select wire:model.live="clientId" :label="__('Client')">
                <flux:select.option value="">{{ __('All clients I can view') }}</flux:select.option>
                @foreach ($this->clients as $client)
                    <flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="status" :label="__('Deadline status')">
                <flux:select.option value="all">{{ __('All deadline statuses') }}</flux:select.option>
                @foreach (\App\Enums\ObligationStatus::cases() as $state)
                    <flux:select.option :value="$state->value">{{ $state->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="anchorDate" type="date" :label="__('Date to show')" />
            <div class="tbt-view-switcher" role="group" aria-label="{{ __('Calendar layout') }}">
                @foreach ([
                    'month' => ['label' => __('Month'), 'description' => __('Full month grid')],
                    'week' => ['label' => __('Week'), 'description' => __('Seven day grid')],
                    'list' => ['label' => __('List'), 'description' => __('Deadline register')],
                ] as $value => $option)
                    <button
                        type="button"
                        wire:key="schedule-mode-{{ $value }}"
                        wire:click="setMode('{{ $value }}')"
                        wire:loading.attr="disabled"
                        wire:target="setMode"
                        title="{{ $option['description'] }}"
                        @class([
                        'min-h-9 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300',
                        'bg-amber-300 text-amber-950' => $mode === $value,
                        'text-[var(--tbt-muted)] hover:bg-white/5 hover:text-[var(--tbt-text)]' => $mode !== $value,
                    ])
                        aria-pressed="{{ $mode === $value ? 'true' : 'false' }}"
                    >
                        {{ $option['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                @php([$rangeStart, $rangeEnd] = $this->range())
                <p class="text-lg font-semibold text-zinc-100">
                    {{ $mode === 'month' ? \Illuminate\Support\Carbon::parse($anchorDate)->format('F Y') : $rangeStart->format('d M Y').' to '.$rangeEnd->format('d M Y') }}
                </p>
                <p class="mt-1 text-sm text-zinc-500">{{ trans_choice('{0} No calendar items|{1} 1 calendar item|[2,*] :count calendar items', $this->calendarEvents->count(), ['count' => $this->calendarEvents->count()]) }}</p>
            </div>
            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previousPeriod">{{ __('Previous') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="goToToday">{{ __('Today') }}</flux:button>
                <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" wire:click="nextPeriod">{{ __('Next') }}</flux:button>
            </div>
        </div>
    </section>

    <section class="mt-7" aria-live="polite" aria-busy="{{ $this->obligations ? 'false' : 'true' }}">
        @if ($mode === 'list')
            <div class="tbt-panel divide-y divide-[var(--tbt-border)] px-5">
                @forelse ($this->calendarEvents as $event)
                    <div class="grid gap-3 py-4 sm:grid-cols-[8rem_minmax(0,1fr)_10rem] sm:items-center">
                        <time class="text-sm font-medium text-zinc-200" datetime="{{ $event['date'] }}">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('d M Y') }}</time>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-100">{{ $event['client_code'] }} / {{ $event['title'] }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $event['detail'] }}</p>
                        </div>
                        <flux:badge class="sm:justify-self-end" :color="$event['status_color']">{{ $event['status_label'] }}</flux:badge>
                    </div>
                @empty
                    <div class="py-14 text-center"><p class="text-sm font-medium text-zinc-200">{{ __('No deadlines in this period') }}</p><p class="mt-2 text-sm text-zinc-500">{{ __('Change the date, deadline status or client to view another period.') }}</p></div>
                @endforelse
            </div>
        @else
            @php($eventsByDay = $this->calendarEvents->groupBy('date'))
            <div class="tbt-panel overflow-x-auto" tabindex="0" aria-label="{{ __('Scrollable schedule grid') }}">
                <div class="{{ $mode === 'month' ? 'min-w-[64rem]' : 'min-w-[56rem]' }}">
                    <div class="grid grid-cols-7 border-b border-[var(--tbt-border)] bg-[var(--tbt-table-head)]">
                        @foreach ([__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday')] as $weekday)
                            <div class="px-3 py-3 text-xs font-medium text-zinc-500">{{ $weekday }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7">
                        @foreach ($this->visibleDays() as $day)
                            @php($dayItems = $eventsByDay->get($day->toDateString(), collect()))
                            <div @class([
                                'min-h-40 border-b border-r border-[var(--tbt-border)] p-3',
                                'bg-amber-300/[0.04]' => $day->isToday(),
                                'opacity-50' => $mode === 'month' && ! $day->isSameMonth(\Illuminate\Support\Carbon::parse($anchorDate)),
                            ])>
                                <div class="flex items-center justify-between gap-2">
                                    <time datetime="{{ $day->toDateString() }}" @class([
                                        'flex size-8 items-center justify-center rounded-full text-sm font-medium',
                                        'bg-amber-300 text-amber-950' => $day->isToday(),
                                        'text-zinc-300' => ! $day->isToday(),
                                    ])>{{ $day->day }}</time>
                                    @if ($dayItems->isNotEmpty())<span class="text-xs text-zinc-600">{{ $dayItems->count() }}</span>@endif
                                </div>
                                <div class="mt-3 space-y-2">
                                    @foreach ($dayItems->take(4) as $event)
                                        <div @class([
                                            'rounded-lg px-3 py-2 ring-1',
                                            'bg-amber-300/10 ring-amber-300/30' => $event['kind'] === 'document',
                                            'bg-sky-400/10 ring-sky-400/30' => $event['kind'] === 'obligation',
                                        ])>
                                            <p class="truncate text-xs font-medium text-zinc-200">{{ $event['client_code'] }}</p>
                                            <p class="mt-1 truncate text-xs text-zinc-500">{{ $event['title'] }}</p>
                                        </div>
                                    @endforeach
                                    @if ($dayItems->count() > 4)
                                        <p class="px-1 text-xs text-zinc-500">{{ __(':count more', ['count' => $dayItems->count() - 4]) }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </section>

    <section class="mt-5" aria-labelledby="client-timeline-heading">
        <div class="tbt-section-heading">
            <div>
            <h2 id="client-timeline-heading">{{ __('Client timeline') }}</h2>
            <p>{{ $clientId === '' ? __('Select a client to view its recent compliance history.') : __('Recent changes to the client, due dates, tasks, filings and payments.') }}</p>
            </div>
        </div>
        @if ($clientId !== '')
            <ol class="tbt-panel divide-y divide-[var(--tbt-border)] px-5">
                @forelse ($this->timelineEvents as $event)
                    <li class="grid gap-2 py-4 sm:grid-cols-[11rem_minmax(0,1fr)]">
                        <time class="text-xs text-zinc-500" datetime="{{ $event['date'] }}">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('d M Y, H:i') }}</time>
                        <div>
                            <p class="text-sm font-medium text-zinc-200">{{ $event['label'] }}</p>
                            <p class="mt-1 text-sm text-zinc-500">{{ $event['detail'] }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-10 text-center text-sm text-zinc-500">{{ __('No activity has been recorded for this client.') }}</li>
                @endforelse
            </ol>
        @endif
    </section>
</div>
