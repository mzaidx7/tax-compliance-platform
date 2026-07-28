{{-- THESIS: A firm schedule is a working ledger of recorded deadlines, not a decorative calendar.
     ANCHOR: The selected period and view mode stay together as one compact control rail.
     COMPOSITION: Filters lead into one continuous month, week or list register, followed by client history.
     DEPTH: Matte ink surfaces use dividers for structure and restrained gold only for today and primary focus.
     SIGNATURE: Every date cell exposes real obligation labels directly, with no hidden hover-only information. --}}
<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <p class="mb-3 text-sm font-medium text-amber-300">{{ app(\App\Tenancy\FirmContext::class)->firm()->name }}</p>
        <h1 class="max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Compliance calendar') }}</h1>
        <p class="mt-5 max-w-3xl text-base leading-7 text-zinc-400">{{ __('View recorded client deadlines by month, week or list. Filter the calendar by client and deadline status.') }}</p>
    </header>

    <section class="mt-8" aria-labelledby="schedule-controls-heading">
        <h2 id="schedule-controls-heading" class="sr-only">{{ __('Schedule controls') }}</h2>
        <div class="grid gap-4 border-y border-white/8 py-5 lg:grid-cols-[minmax(12rem,1fr)_minmax(10rem,0.7fr)_minmax(9rem,0.5fr)_auto] lg:items-end">
            <flux:select wire:model.live="clientId" :label="__('Client')">
                <flux:select.option value="">{{ __('All visible clients') }}</flux:select.option>
                @foreach ($this->clients as $client)
                    <flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="status" :label="__('Obligation state')">
                <flux:select.option value="all">{{ __('All states') }}</flux:select.option>
                @foreach (\App\Enums\ObligationStatus::cases() as $state)
                    <flux:select.option :value="$state->value">{{ $state->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="anchorDate" type="date" :label="__('Reference date')" />
            <div class="flex min-h-11 items-center gap-1 rounded-xl bg-zinc-900 p-1" role="group" aria-label="{{ __('Schedule view') }}">
                @foreach (['month' => __('Month'), 'week' => __('Week'), 'list' => __('List')] as $value => $label)
                    <button type="button" wire:click="$set('mode', '{{ $value }}')" @class([
                        'min-h-9 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300',
                        'bg-amber-300 text-amber-950' => $mode === $value,
                        'text-zinc-400 hover:bg-white/5 hover:text-white' => $mode !== $value,
                    ]) aria-pressed="{{ $mode === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                @php([$rangeStart, $rangeEnd] = $this->range())
                <p class="text-lg font-semibold text-zinc-100">
                    {{ $mode === 'month' ? \Illuminate\Support\Carbon::parse($anchorDate)->format('F Y') : $rangeStart->format('d M Y').' to '.$rangeEnd->format('d M Y') }}
                </p>
                <p class="mt-1 text-sm text-zinc-500">{{ trans_choice('{0} No recorded deadlines|{1} 1 recorded deadline|[2,*] :count recorded deadlines', $this->obligations->count(), ['count' => $this->obligations->count()]) }}</p>
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
            <div class="divide-y divide-white/8 border-y border-white/8">
                @forelse ($this->obligations as $obligation)
                    <div class="grid gap-3 py-4 sm:grid-cols-[8rem_minmax(0,1fr)_10rem] sm:items-center">
                        <time class="text-sm font-medium text-zinc-200" datetime="{{ $obligation->effectiveDueDate()->toDateString() }}">{{ $obligation->effectiveDueDate()->format('d M Y') }}</time>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-100">{{ $obligation->client->internal_code }} / {{ $obligation->obligation_type }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $obligation->period_label ?: __('No period label') }} / {{ trans_choice('{0} No work records|{1} 1 work record|[2,*] :count work records', $obligation->workItems->count(), ['count' => $obligation->workItems->count()]) }}</p>
                        </div>
                        <flux:badge class="sm:justify-self-end" :color="$obligation->status->badgeColor()">{{ $obligation->status->label() }}</flux:badge>
                    </div>
                @empty
                    <div class="py-14 text-center"><p class="text-sm font-medium text-zinc-200">{{ __('No deadlines in this period') }}</p><p class="mt-2 text-sm text-zinc-500">{{ __('Change the date, state or client filter to inspect another range.') }}</p></div>
                @endforelse
            </div>
        @else
            @php($obligationsByDay = $this->obligations->groupBy(fn ($obligation) => $obligation->effectiveDueDate()->toDateString()))
            <div class="overflow-x-auto border-y border-white/8" tabindex="0" aria-label="{{ __('Scrollable schedule grid') }}">
                <div class="{{ $mode === 'month' ? 'min-w-[64rem]' : 'min-w-[56rem]' }}">
                    <div class="grid grid-cols-7 border-b border-white/8">
                        @foreach ([__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday')] as $weekday)
                            <div class="px-3 py-3 text-xs font-medium text-zinc-500">{{ $weekday }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7">
                        @foreach ($this->visibleDays() as $day)
                            @php($dayItems = $obligationsByDay->get($day->toDateString(), collect()))
                            <div @class([
                                'min-h-40 border-b border-r border-white/8 p-3',
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
                                    @foreach ($dayItems->take(4) as $obligation)
                                        <div class="rounded-lg bg-zinc-900 px-3 py-2">
                                            <p class="truncate text-xs font-medium text-zinc-200">{{ $obligation->client->internal_code }}</p>
                                            <p class="mt-1 truncate text-xs text-zinc-500">{{ $obligation->obligation_type }}</p>
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

    <section class="mt-12" aria-labelledby="client-timeline-heading">
        <div class="mb-4">
            <h2 id="client-timeline-heading" class="text-lg font-semibold text-zinc-100">{{ __('Client timeline') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ $clientId === '' ? __('Select a client to view its recent compliance history.') : __('Recent client, deadline, work, filing and payment activity. Reasons remain available in the related records.') }}</p>
        </div>
        @if ($clientId !== '')
            <ol class="divide-y divide-white/8 border-y border-white/8">
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
