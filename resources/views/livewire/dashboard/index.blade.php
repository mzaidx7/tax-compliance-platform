<div class="tbt-page">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-7" :heading="session('status')" />
    @endif

    <header class="tbt-page-header">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="tbt-page-kicker">
                    {{ app(\App\Tenancy\FirmContext::class)->firm()->name }}
                </p>
                <h1 class="tbt-page-title">
                    {{ __("Today's compliance work") }}
                </h1>
                <p class="tbt-page-copy">
                    {{ __('See what needs attention today, which clients are waiting, and which VAT Returns, Corporate Tax Returns or documents are coming up.') }}
                </p>
            </div>

            @can('viewAny', \App\Models\WorkItem::class)
                <flux:button :href="route('work-items.index')" variant="filled" icon="queue-list" wire:navigate>
                    {{ __('Open task list') }}
                </flux:button>
            @endcan
        </div>
    </header>

    @if ($showTutorialPrompt)
        <section class="tbt-tutorial-invite mt-5" aria-labelledby="tutorial-invite-heading">
            <div class="tbt-tutorial-invite__mark" aria-hidden="true">
                <flux:icon.map class="size-6" />
            </div>
            <div class="min-w-0">
                <p class="tbt-page-kicker">{{ __('New to the platform?') }}</p>
                <h2 id="tutorial-invite-heading">{{ __('See the complete compliance workflow in four minutes') }}</h2>
                <p>{{ __('Learn how the dashboard, client import, document expiry, VAT and Corporate Tax deadlines, calendar and client tasks work together.') }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <flux:button :href="route('tutorial.index')" variant="filled" icon="play" wire:navigate>
                        {{ __('Start tutorial') }}
                    </flux:button>
                    <flux:button variant="ghost" wire:click="dismissTutorialPrompt">
                        {{ __('Not now') }}
                    </flux:button>
                </div>
            </div>
            <ul class="tbt-tutorial-invite__topics" aria-label="{{ __('Tutorial topics') }}">
                <li>{{ __('Portfolio view') }}</li>
                <li>{{ __('Client records') }}</li>
                <li>{{ __('Deadlines and tasks') }}</li>
            </ul>
        </section>
    @endif

    <section class="tbt-filter-panel mt-5" aria-labelledby="dashboard-filter-heading">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 id="dashboard-filter-heading" class="tbt-panel-title">{{ __('Dashboard filters') }}</h2>
                <p class="tbt-panel-copy">{{ __('Choose a month, client, tax type or team member to narrow the results below.') }}</p>
            </div>
            <flux:icon.funnel class="size-5 text-amber-300" aria-hidden="true" />
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 xl:items-end">
            <flux:input wire:model.live.debounce.300ms="clientSearch" :label="__('Search clients')" :placeholder="__('Code or legal name')" maxlength="100" />
            <flux:input wire:model.live="selectedMonth" type="month" :label="__('Due month')" />
            <flux:select wire:model.live="category" :label="__('Category')">
                <flux:select.option value="all">{{ __('All categories') }}</flux:select.option>
                <flux:select.option value="vat">{{ __('VAT') }}</flux:select.option>
                <flux:select.option value="corporate-tax">{{ __('Corporate Tax') }}</flux:select.option>
                <flux:select.option value="documents">{{ __('Documents') }}</flux:select.option>
            </flux:select>
                <flux:select wire:model.live="clientId" :label="__('Clients')">
                    <flux:select.option value="">{{ __('All clients I can view') }}</flux:select.option>
                @foreach ($this->clients as $client)
                    <flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="teamMembershipId" :label="__('Responsible team member')">
                <flux:select.option value="">{{ __('All team members') }}</flux:select.option>
                @foreach ($this->teamMembers as $membership)
                    <flux:select.option :value="$membership->id">{{ $membership->user->name }}</flux:select.option>
                @endforeach
            </flux:select>
                <flux:select wire:model.live="attentionStatus" :label="__('Due date status')">
                <flux:select.option value="all">{{ __('Upcoming and overdue') }}</flux:select.option>
                <flux:select.option value="upcoming">{{ __('Upcoming only') }}</flux:select.option>
                <flux:select.option value="overdue">{{ __('Overdue only') }}</flux:select.option>
            </flux:select>
                <flux:select wire:model.live="horizonDays" :label="__('Show deadlines within')">
                @foreach ([7, 14, 30, 60, 90] as $days)
                    <flux:select.option :value="$days">{{ trans_choice('{1} 1 day|[2,*] :count days', $days, ['count' => $days]) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="selectedSavedFilterId" :label="__('Your saved views')">
                <flux:select.option value="">{{ __('Select saved filter') }}</flux:select.option>
                @foreach ($this->savedFilters as $savedFilter)
                    <flux:select.option :value="$savedFilter->id">{{ $savedFilter->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex gap-2 md:col-span-2 xl:col-span-1">
                <flux:button variant="ghost" wire:click="applySavedFilter" :disabled="$selectedSavedFilterId === ''">{{ __('Apply') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteSavedFilter" :disabled="$selectedSavedFilterId === ''" wire:confirm="{{ __('Delete this saved filter?') }}">{{ __('Delete') }}</flux:button>
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(12rem,1fr)_auto] sm:items-end">
            <flux:input wire:model="savedFilterName" :label="__('Save current dashboard view as')" :placeholder="__('Quarter-end clients')" maxlength="80" />
            <flux:button variant="filled" wire:click="saveFilter" :disabled="trim($savedFilterName) === ''">{{ __('Save filter') }}</flux:button>
        </div>
    </section>

    <section class="mt-5" aria-labelledby="portfolio-month-heading">
        <div class="tbt-section-heading">
            <div>
                <h2 id="portfolio-month-heading">{{ __('Selected month') }}</h2>
                <p>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $selectedMonth)?->format('F Y') }} / {{ __('All clients I can view') }}</p>
            </div>
            <a href="{{ route('schedule.index') }}" class="text-sm font-medium text-amber-300 hover:text-amber-200" wire:navigate>{{ __('Open calendar') }}</a>
        </div>
        <dl class="tbt-metric-grid">
            @foreach ([
                [__('VAT due'), $this->summary['vat_due'], '#38bdf8'],
                [__('CT due'), $this->summary['corporate_tax_due'], '#a78bfa'],
                [__('Documents expiring'), $this->summary['documents_expiring'], '#d4a64a'],
                [__('Overdue'), $this->summary['overdue'], '#fb7185'],
                [__('Emails to review'), $this->summary['reminders_awaiting_review'], '#fbbf24'],
                [__('Missing information'), $this->summary['missing_information'], '#f87171'],
            ] as [$label, $count, $accent])
                <div class="tbt-metric" style="--metric-accent: {{ $accent }}">
                    <dt class="tbt-metric-label">{{ $label }}</dt>
                    <dd class="tbt-metric-value">{{ $count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="tbt-panel mt-5" aria-labelledby="workload-strip-heading">
        <div class="tbt-panel-header">
            <div>
                <h2 id="workload-strip-heading" class="tbt-panel-title">{{ __('12-month workload') }}</h2>
                <p class="tbt-panel-copy">{{ __('VAT, Corporate Tax and document dates. Exact counts are shown below each month.') }}</p>
            </div>
            <div class="hidden items-center gap-4 text-xs text-[var(--tbt-muted)] sm:flex" aria-hidden="true">
                <span><i class="mr-1 inline-block size-2 rounded-sm bg-sky-400"></i>{{ __('VAT') }}</span>
                <span><i class="mr-1 inline-block size-2 rounded-sm bg-violet-400"></i>{{ __('CT') }}</span>
                <span><i class="mr-1 inline-block size-2 rounded-sm bg-amber-300"></i>{{ __('Documents') }}</span>
            </div>
        </div>
        @php
            $maximumWorkload = max(1, collect($this->workloadStrip)->max('total'));
        @endphp
        <div class="overflow-x-auto px-4 py-5">
            <ol class="grid min-w-[56rem] grid-cols-12 gap-2">
                @foreach ($this->workloadStrip as $month)
                    <li class="flex min-h-32 flex-col justify-end" aria-label="{{ __(':month: :vat VAT, :ct Corporate Tax, :documents documents', ['month' => $month['label'], 'vat' => $month['vat'], 'ct' => $month['corporate_tax'], 'documents' => $month['documents']]) }}">
                        <div class="flex h-16 items-end gap-px rounded-t bg-white/[0.025] px-2" aria-hidden="true">
                            <span class="w-1/3 bg-sky-400/80" style="height: {{ max(2, round(($month['vat'] / $maximumWorkload) * 100)) }}%"></span>
                            <span class="w-1/3 bg-violet-400/80" style="height: {{ max(2, round(($month['corporate_tax'] / $maximumWorkload) * 100)) }}%"></span>
                            <span class="w-1/3 bg-amber-300/80" style="height: {{ max(2, round(($month['documents'] / $maximumWorkload) * 100)) }}%"></span>
                        </div>
                        <p class="mt-2 text-center text-xs font-medium text-zinc-400">{{ $month['label'] }}</p>
                        <p class="mt-1 text-center font-mono text-xs text-zinc-600">{{ $month['total'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="mt-5" aria-labelledby="portfolio-priority-heading">
        <div class="tbt-section-heading">
            <div>
                <h2 id="portfolio-priority-heading">{{ __('Deadlines and expiring documents') }}</h2>
                <p>{{ __('Shows the selected month plus anything overdue from an earlier month.') }}</p>
            </div>
            <p class="text-xs text-zinc-500">{{ __(':count items', ['count' => $this->portfolioItems->total()]) }}</p>
        </div>
        <div class="tbt-table-shell overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-white/8">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-zinc-500">{{ __('Client') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold text-zinc-500">{{ __('Category') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold text-zinc-500">{{ __('Item') }}</th>
                            <th class="px-4 py-3 text-xs font-semibold text-zinc-500">{{ __('Due or expiry date') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold text-zinc-500">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/8">
                    @forelse ($this->portfolioItems as $item)
                        @php
                            $isOverdue = \Illuminate\Support\Carbon::parse($item->event_date)->isBefore(today());
                        @endphp
                        <tr>
                            <td class="px-4 py-4">
                                <a href="{{ route('clients.show', $item->client_id) }}" class="font-medium text-zinc-200 hover:text-amber-200" wire:navigate>{{ $item->client_code }}</a>
                                <p class="mt-1 max-w-64 truncate text-xs text-zinc-600">{{ $item->client_name }}</p>
                            </td>
                            <td class="px-4 py-4"><flux:badge :color="$item->item_kind === 'document' ? 'amber' : 'sky'">{{ $item->item_kind === 'document' ? __('Document') : __('Tax return') }}</flux:badge></td>
                            <td class="px-4 py-4"><p class="text-zinc-200">{{ $item->title }}</p><p class="mt-1 text-xs text-zinc-500">{{ $item->detail }}</p></td>
                            <td class="px-4 py-4 font-mono {{ $isOverdue ? 'text-red-300' : 'text-zinc-300' }}">{{ \Illuminate\Support\Carbon::parse($item->event_date)->format('d M Y') }}</td>
                            <td class="px-4 py-4"><flux:badge :color="$isOverdue ? 'red' : 'zinc'">{{ $isOverdue ? __('Overdue') : str($item->status)->replace('_', ' ')->title() }}</flux:badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center"><p class="text-sm font-medium text-zinc-200">{{ __('No priority items in this view') }}</p><p class="mt-2 text-sm text-zinc-500">{{ __('Change the month, category or client filters.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->portfolioItems->hasPages())
            <div class="mt-5">{{ $this->portfolioItems->links() }}</div>
        @endif
    </section>

    <section class="mt-5" aria-labelledby="operational-summary-heading">
        <div class="tbt-section-heading">
            <div>
                <h2 id="operational-summary-heading">{{ __('What needs attention') }}</h2>
                <p>{{ __('A quick count of deadlines, payments and client tasks that need action.') }}</p>
            </div>
            <p class="text-xs text-zinc-500">{{ __('Due soon means today through the next :days days', ['days' => $horizonDays]) }}</p>
        </div>

        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="tbt-panel flex min-h-40 flex-col justify-between p-5">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Deadlines due soon') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Open deadlines within the selected number of days.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-white">{{ $this->summary['due_soon'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-40 flex-col justify-between p-5">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Overdue deadlines') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Open deadlines with a due date that has passed.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['overdue'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-40 flex-col justify-between p-5">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('High-priority tasks') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Client tasks your team has marked as needing urgent attention.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['high_risk'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-40 flex-col justify-between p-5">
                <div>
                    <dt class="text-sm font-medium text-zinc-200">{{ __('Overdue payments') }}</dt>
                    <dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Payments that your team has marked as overdue.') }}</dd>
                </div>
                <dd class="mt-5 text-4xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['overdue_payments'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-36 flex-col justify-between p-5">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Waiting for client') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Tasks waiting for client documents, information or approval.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-amber-300">{{ $this->summary['awaiting_client'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-36 flex-col justify-between p-5">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Waiting for review') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Tasks submitted to the assigned reviewer.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-white">{{ $this->summary['under_review'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-36 flex-col justify-between p-5">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Unassigned tasks') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Open client tasks with no assigned team member.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-red-300">{{ $this->summary['unassigned'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-36 flex-col justify-between p-5">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Open tasks') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('All unfinished client tasks you can view.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-white">{{ $this->summary['active_workload'] }}</dd>
            </div>
            <div class="tbt-panel flex min-h-36 flex-col justify-between p-5 xl:col-span-2">
                <div><dt class="text-sm font-medium text-zinc-200">{{ __('Client emails awaiting review') }}</dt><dd class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Prepared reminders that need approval before they are sent.') }}</dd></div>
                <dd class="mt-5 text-3xl font-semibold tracking-[-0.03em] text-amber-300">{{ $this->summary['reminders_awaiting_review'] }}</dd>
            </div>
        </dl>
    </section>

    <section class="tbt-panel mt-5" aria-labelledby="client-reminder-review-heading">
        <div class="tbt-panel-header">
            <div>
                <h2 id="client-reminder-review-heading" class="tbt-panel-title">{{ __('Client reminder review') }}</h2>
                <p class="tbt-panel-copy">{{ __('Review the category, client and relevant date. Sensitive document and registration numbers are never included in the email.') }}</p>
            </div>
            <flux:badge color="amber">{{ $this->summary['reminders_awaiting_review'] }}</flux:badge>
        </div>
        <div class="divide-y divide-[var(--tbt-border)]">
            @forelse ($this->remindersAwaitingReview as $reminder)
                <div class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_9rem_auto] md:items-center">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-100">{{ $reminder->client->internal_code }} / {{ $reminder->client->legal_name }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $reminder->category->label() }} / {{ __('Due or expiry date: :date', ['date' => $reminder->event_date->format('d M Y')]) }}</p>
                    </div>
                    <flux:badge color="amber">{{ $reminder->status->label() }}</flux:badge>
                    @can('update', $reminder->client)
                        <flux:button size="sm" variant="filled" wire:click="approveReminder('{{ $reminder->id }}')" wire:confirm="{{ __('Approve and queue this client email?') }}">{{ __('Approve email') }}</flux:button>
                    @endcan
                </div>
            @empty
                <div class="tbt-empty">
                    <div>
                    <p class="text-sm font-medium text-zinc-200">{{ __('No client emails are awaiting review') }}</p>
                    <p class="mt-2 text-sm text-zinc-500">{{ __('New reminders will appear here when they reach their scheduled review date.') }}</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.55fr)]">
        <section class="tbt-panel" aria-labelledby="deadline-queue-heading">
            <div class="tbt-panel-header">
                <div>
                <h2 id="deadline-queue-heading" class="tbt-panel-title">{{ __('Upcoming and overdue dates') }}</h2>
                <p class="tbt-panel-copy">{{ __('Overdue dates appear first, followed by the next due dates.') }}</p>
                </div>
                @can('viewAny', \App\Models\Obligation::class)
                    <flux:button size="sm" variant="ghost" :href="route('obligations.index')" wire:navigate>{{ __('View deadlines') }}</flux:button>
                @endcan
            </div>

            <div class="divide-y divide-[var(--tbt-border)]">
                @forelse ($this->priorityObligations as $obligation)
                    @php
                        $effectiveDueDate = $obligation->effectiveDueDate();
                        $days = today()->diffInDays($effectiveDueDate, false);
                    @endphp
                    <div class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_9rem] sm:items-center">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-200">
                                {{ $obligation->client->internal_code }} · {{ $obligation->obligation_type }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $obligation->period_label ?: __('Tax period not recorded') }}
                                ·
                                {{ trans_choice('{0} No tasks|{1} 1 task|[2,*] :count tasks', $obligation->workItems->count(), ['count' => $obligation->workItems->count()]) }}
                            </p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-sm font-medium {{ $days < 0 ? 'text-red-300' : 'text-zinc-200' }}">
                                {{ $effectiveDueDate->format('d M Y') }}
                            </p>
                            @if (! $effectiveDueDate->isSameDay($obligation->statutory_due_date))
                                <p class="mt-1 text-xs text-amber-300">
                                    {{ __('Updated due date · Original due date: :date', ['date' => $obligation->statutory_due_date->format('d M Y')]) }}
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
                    <div class="tbt-empty">
                        <div>
                        <p class="text-sm font-medium text-zinc-200">{{ __('No deadlines need attention') }}</p>
                        <p class="mt-2 text-sm text-zinc-500">{{ __('No open deadline is overdue or due in the next 30 days.') }}</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="grid gap-5">
            <section class="tbt-panel" aria-labelledby="high-risk-heading">
            <div class="tbt-panel-header"><h2 id="high-risk-heading" class="tbt-panel-title">{{ __('High-priority tasks') }}</h2></div>
                <div class="space-y-3 p-4">
                    @forelse ($this->highRiskWork as $workItem)
                        <div class="rounded-xl bg-zinc-900/70 px-4 py-3 ring-1 ring-white/8">
                            <p class="truncate text-sm font-medium text-zinc-200">
                                {{ $workItem->obligation->client->internal_code }} · {{ $workItem->obligation->obligation_type }}
                            </p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <flux:badge :color="$workItem->status->badgeColor()">{{ $workItem->status->label() }}</flux:badge>
                                <span class="text-xs text-red-300">{{ __('Urgent attention') }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm leading-6 text-zinc-500">{{ __('No open task is marked for urgent attention.') }}</p>
                    @endforelse
                </div>
            </section>

            <section class="tbt-panel" aria-labelledby="overdue-payments-heading">
                <div class="tbt-panel-header"><h2 id="overdue-payments-heading" class="tbt-panel-title">{{ __('Overdue payment records') }}</h2></div>
                <div class="space-y-3 p-4">
                    @forelse ($this->overduePayments as $payment)
                        <div class="rounded-xl bg-zinc-900/70 px-4 py-3 ring-1 ring-white/8">
                            <p class="truncate text-sm font-medium text-zinc-200">
                                {{ $payment->obligation->client->internal_code }} · {{ $payment->obligation->obligation_type }}
                            </p>
                            <p class="mt-2 text-xs text-zinc-500">{{ __('Marked overdue · Due date: :date', ['date' => $payment->obligation->effectiveDueDate()->format('d M Y')]) }}</p>
                        </div>
                    @empty
                        <p class="text-sm leading-6 text-zinc-500">{{ __('No visible payment record is marked overdue.') }}</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    <section class="mt-5" aria-labelledby="work-queues-heading">
        <div class="tbt-section-heading">
                <div><h2 id="work-queues-heading">{{ __('Tasks by status') }}</h2><p>{{ __('Open the task list to update assignments or progress.') }}</p></div>
                <flux:button size="sm" variant="ghost" :href="route('work-items.index')" wire:navigate>{{ __('Open task list') }}</flux:button>
        </div>
        <div class="grid gap-3 lg:grid-cols-3">
            @foreach ([
                [__('Waiting for client'), $this->awaitingClientWork, __('No task is waiting for client action.')],
                [__('Waiting for review'), $this->underReviewWork, __('No task is waiting for review.')],
                [__('Unassigned'), $this->unassignedWork, __('No open task is unassigned.')],
            ] as [$heading, $items, $empty])
                <details class="tbt-accordion" @if ($loop->first) open @endif>
                    <summary>{{ $heading }}</summary>
                    <div class="divide-y divide-[var(--tbt-border)] px-4">
                        @forelse ($items as $workItem)
                            <div class="py-3">
                                <p class="truncate text-sm font-medium text-zinc-200">{{ $workItem->obligation->client->internal_code }} / {{ $workItem->obligation->obligation_type }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $workItem->status->label() }}</p>
                            </div>
                        @empty
                            <p class="py-6 text-sm leading-6 text-zinc-500">{{ $empty }}</p>
                        @endforelse
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    <section class="mt-5" aria-labelledby="workload-heading">
        <div class="tbt-section-heading"><div><h2 id="workload-heading">{{ __('Team workload') }}</h2><p>{{ __('Current assignments for work that you have permission to view.') }}</p></div></div>
        <div class="tbt-table-shell overflow-x-auto">
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
