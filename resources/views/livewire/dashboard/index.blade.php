<div class="mx-auto w-full max-w-7xl">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-7" :heading="session('status')" />
    @endif

    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="mb-3 text-sm font-medium text-amber-300">
                    {{ app(\App\Tenancy\FirmContext::class)->firm()->name }}
                </p>
                <h1 class="max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                    {{ __('Operational horizon') }}
                </h1>
                <p class="mt-5 max-w-3xl text-base leading-7 text-zinc-400">
                    {{ __('A firm-scoped view of recorded deadlines, work risk and overdue payment state. These measures describe stored operations and do not calculate or guarantee compliance.') }}
                </p>
            </div>

            @can('viewAny', \App\Models\WorkItem::class)
                <flux:button :href="route('work-items.index')" variant="filled" icon="queue-list" wire:navigate>
                    {{ __('Open work register') }}
                </flux:button>
            @endcan
        </div>
    </header>

    <section class="mt-8" aria-labelledby="operational-summary-heading">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="operational-summary-heading" class="text-lg font-semibold text-zinc-100">{{ __('Recorded priorities') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Counts stay separate because each operational state has a different meaning.') }}</p>
            </div>
            <p class="text-xs text-zinc-500">{{ __('Due soon means today through the next 30 days') }}</p>
        </div>

        <dl class="divide-y divide-white/8 border-y border-white/8">
            <div class="grid gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_7rem] sm:items-center">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Open obligations due soon') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-500">{{ __('Stored statutory due dates from today through 30 days.') }}</dd>
                </div>
                <dd class="text-3xl font-semibold tracking-[-0.03em] text-white sm:text-right">{{ $this->summary['due_soon'] }}</dd>
            </div>
            <div class="grid gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_7rem] sm:items-center">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Open obligations past due') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-500">{{ __('Open records whose stored statutory due date has passed.') }}</dd>
                </div>
                <dd class="text-3xl font-semibold tracking-[-0.03em] text-red-300 sm:text-right">{{ $this->summary['overdue'] }}</dd>
            </div>
            <div class="grid gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_7rem] sm:items-center">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Active work marked high risk') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-500">{{ __('Explicitly recorded risk on non-terminal work. No risk is inferred.') }}</dd>
                </div>
                <dd class="text-3xl font-semibold tracking-[-0.03em] text-red-300 sm:text-right">{{ $this->summary['high_risk'] }}</dd>
            </div>
            <div class="grid gap-2 py-4 sm:grid-cols-[minmax(0,1fr)_7rem] sm:items-center">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Payments recorded overdue') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-500">{{ __('Explicit payment state only. This platform does not initiate or confirm transfers.') }}</dd>
                </div>
                <dd class="text-3xl font-semibold tracking-[-0.03em] text-red-300 sm:text-right">{{ $this->summary['overdue_payments'] }}</dd>
            </div>
        </dl>
    </section>

    <div class="mt-10 grid gap-10 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.6fr)]">
        <section aria-labelledby="deadline-queue-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h2 id="deadline-queue-heading" class="text-lg font-semibold text-zinc-100">{{ __('Deadline queue') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Overdue first, then the next recorded deadlines.') }}</p>
                </div>
                @can('viewAny', \App\Models\Obligation::class)
                    <flux:button size="sm" variant="ghost" :href="route('obligations.index')" wire:navigate>{{ __('View obligations') }}</flux:button>
                @endcan
            </div>

            <div class="divide-y divide-white/8 border-y border-white/8">
                @forelse ($this->priorityObligations as $obligation)
                    @php
                        $effectiveDueDate = $obligation->effectiveDueDate();
                        $days = today()->diffInDays($effectiveDueDate, false);
                    @endphp
                    <div class="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_9rem] sm:items-center">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-200">
                                {{ $obligation->client->internal_code }} · {{ $obligation->obligation_type }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $obligation->period_label ?: __('No period label') }}
                                ·
                                {{ trans_choice('{0} No work records|{1} 1 work record|[2,*] :count work records', $obligation->workItems->count(), ['count' => $obligation->workItems->count()]) }}
                            </p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-sm font-medium {{ $days < 0 ? 'text-red-300' : 'text-zinc-200' }}">
                                {{ $effectiveDueDate->format('d M Y') }}
                            </p>
                            @if (! $effectiveDueDate->isSameDay($obligation->statutory_due_date))
                                <p class="mt-1 text-xs text-amber-300">
                                    {{ __('Effective override · Statutory :date', ['date' => $obligation->statutory_due_date->format('d M Y')]) }}
                                </p>
                            @endif
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $days < 0
                                    ? trans_choice('{1} 1 day overdue|[2,*] :count days overdue', abs($days), ['count' => abs($days)])
                                    : trans_choice('{0} Due today|{1} Due tomorrow|[2,*] Due in :count days', $days, ['count' => $days]) }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center">
                        <p class="text-sm font-medium text-zinc-200">{{ __('No deadlines need attention') }}</p>
                        <p class="mt-2 text-sm text-zinc-500">{{ __('No visible open obligation is overdue or due in the next 30 days.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-8">
            <section aria-labelledby="high-risk-heading">
                <h2 id="high-risk-heading" class="text-sm font-semibold text-zinc-100">{{ __('High-risk work') }}</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($this->highRiskWork as $workItem)
                        <div class="rounded-xl bg-zinc-900/70 px-4 py-3 ring-1 ring-white/8">
                            <p class="truncate text-sm font-medium text-zinc-200">
                                {{ $workItem->obligation->client->internal_code }} · {{ $workItem->obligation->obligation_type }}
                            </p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <flux:badge :color="$workItem->status->badgeColor()">{{ $workItem->status->label() }}</flux:badge>
                                <span class="text-xs text-red-300">{{ __('High risk') }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm leading-6 text-zinc-500">{{ __('No visible active work is marked high risk.') }}</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="overdue-payments-heading">
                <h2 id="overdue-payments-heading" class="text-sm font-semibold text-zinc-100">{{ __('Overdue payment records') }}</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($this->overduePayments as $payment)
                        <div class="rounded-xl bg-zinc-900/70 px-4 py-3 ring-1 ring-white/8">
                            <p class="truncate text-sm font-medium text-zinc-200">
                                {{ $payment->obligation->client->internal_code }} · {{ $payment->obligation->obligation_type }}
                            </p>
                            <p class="mt-2 text-xs text-zinc-500">{{ __('Recorded overdue · Effective due :date', ['date' => $payment->obligation->effectiveDueDate()->format('d M Y')]) }}</p>
                        </div>
                    @empty
                        <p class="text-sm leading-6 text-zinc-500">{{ __('No visible payment record is marked overdue.') }}</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
