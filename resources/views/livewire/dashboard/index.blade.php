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
                    {{ __('Compliance overview') }}
                </h1>
                <p class="mt-5 max-w-3xl text-base leading-7 text-zinc-400">
                    {{ __('See upcoming deadlines, overdue items, work risks and payment follow-ups for your firm. The figures come from information recorded by your team.') }}
                </p>
            </div>

            @can('viewAny', \App\Models\WorkItem::class)
                <flux:button :href="route('work-items.index')" variant="filled" icon="queue-list" wire:navigate>
                    {{ __('Open work tracker') }}
                </flux:button>
            @endcan
        </div>
    </header>

    <section class="mt-8 border-y border-white/8 py-5" aria-labelledby="dashboard-filter-heading">
        <h2 id="dashboard-filter-heading" class="sr-only">{{ __('Dashboard filters') }}</h2>
        <div class="grid gap-4 lg:grid-cols-[minmax(12rem,1fr)_10rem_minmax(12rem,1fr)_auto] lg:items-end">
            <flux:select wire:model.live="clientId" :label="__('Client scope')">
                <flux:select.option value="">{{ __('All visible clients') }}</flux:select.option>
                @foreach ($this->clients as $client)
                    <flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="horizonDays" :label="__('Due horizon')">
                @foreach ([7, 14, 30, 60, 90] as $days)
                    <flux:select.option :value="$days">{{ trans_choice('{1} 1 day|[2,*] :count days', $days, ['count' => $days]) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="selectedSavedFilterId" :label="__('Your saved filters')">
                <flux:select.option value="">{{ __('Select saved filter') }}</flux:select.option>
                @foreach ($this->savedFilters as $savedFilter)
                    <flux:select.option :value="$savedFilter->id">{{ $savedFilter->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex gap-2">
                <flux:button variant="ghost" wire:click="applySavedFilter" :disabled="$selectedSavedFilterId === ''">{{ __('Apply') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteSavedFilter" :disabled="$selectedSavedFilterId === ''" wire:confirm="{{ __('Delete this saved filter?') }}">{{ __('Delete') }}</flux:button>
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(12rem,1fr)_auto] sm:items-end">
            <flux:input wire:model="savedFilterName" :label="__('Save current dashboard view as')" :placeholder="__('Quarter-end clients')" maxlength="80" />
            <flux:button variant="filled" wire:click="saveFilter" :disabled="trim($savedFilterName) === ''">{{ __('Save filter') }}</flux:button>
        </div>
    </section>

    <section class="mt-8" aria-labelledby="operational-summary-heading">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="operational-summary-heading" class="text-lg font-semibold text-zinc-100">{{ __('What needs attention') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Each number shows a separate type of deadline or work item.') }}</p>
            </div>
            <p class="text-xs text-zinc-500">{{ __('Due soon means today through the next :days days', ['days' => $horizonDays]) }}</p>
        </div>

        <dl class="grid border-y border-white/8 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex min-h-40 flex-col justify-between border-b border-white/8 px-4 py-5 sm:border-r xl:border-b-0">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Deadlines due soon') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Open deadlines within the selected number of days.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-white">{{ $this->summary['due_soon'] }}</dd>
            </div>
            <div class="flex min-h-40 flex-col justify-between border-b border-white/8 px-4 py-5 xl:border-b-0 xl:border-r">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Overdue deadlines') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Open deadlines with a due date that has passed.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['overdue'] }}</dd>
            </div>
            <div class="flex min-h-40 flex-col justify-between border-b border-white/8 px-4 py-5 sm:border-r xl:border-b-0">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('High-risk work') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Work that your team has marked as high risk.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['high_risk'] }}</dd>
            </div>
            <div class="flex min-h-40 flex-col justify-between border-b border-white/8 px-4 py-5 xl:border-b-0 xl:border-r">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Overdue payments') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Payments that your team has marked as overdue.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['overdue_payments'] }}</dd>
            </div>
            <div class="flex min-h-36 flex-col justify-between border-b border-white/8 px-4 py-5 sm:border-r">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Work awaiting client') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Awaiting client or client approval.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-amber-300">{{ $this->summary['awaiting_client'] }}</dd>
            </div>
            <div class="flex min-h-36 flex-col justify-between border-b border-white/8 px-4 py-5 xl:border-r">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Work under review') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Submitted to an assigned reviewer.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-white">{{ $this->summary['under_review'] }}</dd>
            </div>
            <div class="flex min-h-36 flex-col justify-between border-b border-white/8 px-4 py-5 sm:border-b-0 sm:border-r">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Unassigned work') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Active work with no assigned team member.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['unassigned'] }}</dd>
            </div>
            <div class="flex min-h-36 flex-col justify-between px-4 py-5">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Active workload') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Open work that you have permission to view.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-white">{{ $this->summary['active_workload'] }}</dd>
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
                    <flux:button size="sm" variant="ghost" :href="route('obligations.index')" wire:navigate>{{ __('View deadlines') }}</flux:button>
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
                        <p class="mt-2 text-sm text-zinc-500">{{ __('No open deadline is overdue or due in the next 30 days.') }}</p>
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

    <section class="mt-12" aria-labelledby="work-queues-heading">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div><h2 id="work-queues-heading" class="text-lg font-semibold text-zinc-100">{{ __('Work by stage') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ __('Open the work tracker to update assignments or progress.') }}</p></div>
            <flux:button size="sm" variant="ghost" :href="route('work-items.index')" wire:navigate>{{ __('Open work tracker') }}</flux:button>
        </div>
        <div class="grid gap-8 lg:grid-cols-3">
            @foreach ([
                [__('Awaiting client'), $this->awaitingClientWork, __('No visible work is awaiting client action.')],
                [__('Under review'), $this->underReviewWork, __('No visible work is under review.')],
                [__('Unassigned'), $this->unassignedWork, __('No visible active work is unassigned.')],
            ] as [$heading, $items, $empty])
                <section>
                    <h3 class="text-sm font-semibold text-zinc-200">{{ $heading }}</h3>
                    <div class="mt-3 divide-y divide-white/8 border-y border-white/8">
                        @forelse ($items as $workItem)
                            <div class="py-3">
                                <p class="truncate text-sm font-medium text-zinc-200">{{ $workItem->obligation->client->internal_code }} / {{ $workItem->obligation->obligation_type }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $workItem->status->label() }}</p>
                            </div>
                        @empty
                            <p class="py-6 text-sm leading-6 text-zinc-500">{{ $empty }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </section>

    <section class="mt-12" aria-labelledby="workload-heading">
        <div class="mb-4"><h2 id="workload-heading" class="text-lg font-semibold text-zinc-100">{{ __('Team workload') }}</h2><p class="mt-1 text-sm text-zinc-500">{{ __('Current assignments for work that you have permission to view.') }}</p></div>
        <div class="overflow-x-auto border-y border-white/8">
            <table class="min-w-full divide-y divide-white/8 text-left text-sm">
                <thead><tr><th class="px-4 py-3 text-xs font-semibold text-zinc-400">{{ __('Member') }}</th><th class="px-4 py-3 text-xs font-semibold text-zinc-400">{{ __('Preparer') }}</th><th class="px-4 py-3 text-xs font-semibold text-zinc-400">{{ __('Reviewer') }}</th><th class="px-4 py-3 text-xs font-semibold text-zinc-400">{{ __('Manager') }}</th><th class="px-4 py-3 text-xs font-semibold text-zinc-400">{{ __('Total assignments') }}</th></tr></thead>
                <tbody class="divide-y divide-white/8">
                    @forelse ($this->workloadByMember as $row)
                        <tr><td class="px-4 py-3 font-medium text-zinc-200">{{ $row['name'] }}</td><td class="px-4 py-3 text-zinc-400">{{ $row['preparer'] }}</td><td class="px-4 py-3 text-zinc-400">{{ $row['reviewer'] }}</td><td class="px-4 py-3 text-zinc-400">{{ $row['manager'] }}</td><td class="px-4 py-3 text-zinc-200">{{ $row['total'] }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-zinc-500">{{ __('No visible active assignments.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
