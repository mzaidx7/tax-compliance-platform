{{--
THESIS: A due date is accepted only when its client, origin, verification and internal target stay visible together.
OWN-WORLD: Matte ink ledger rows, silver date hierarchy and restrained gold for controlled work actions.
STORY: A team member sees only relevant work, advances an allowed state and leaves durable evidence.
FIRST VIEWPORT: The chronological register owns the wide field while authorised managers retain the manual-entry station.
FORM: Deadline review rail, grounded structure seven, seed 75cba0e2.
--}}
<div class="tbt-page">
    <header class="tbt-page-header">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="tbt-page-kicker">{{ $this->currentFirmName }}</p>
                <h1 class="tbt-page-title">
                    {{ __('Tax and compliance deadlines') }}
                </h1>
                <p class="tbt-page-copy">
                    {{ __('See each client filing or renewal date, who is responsible and what still needs to be done.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:badge color="amber" icon="pencil-square">{{ __('Dates checked by your team') }}</flux:badge>
            </div>
        </div>
    </header>

    <div @class([
        'mt-5 grid gap-5',
        'min-[1900px]:grid-cols-[minmax(0,1fr)_23rem]' => Gate::allows('create', \App\Models\Obligation::class),
    ])>
        <section aria-labelledby="obligation-register-heading">
            <div class="tbt-section-heading">
                <div>
                    <h2 id="obligation-register-heading">{{ __('Deadline list') }}</h2>
                    <p>{{ __('Open a client card to update the due date, task progress, filing, payment or tax details.') }}</p>
                </div>
                <div class="w-full sm:max-w-xs">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        :label="__('Search deadlines')"
                        placeholder="Client, type or period"
                        icon="magnifying-glass"
                    />
                </div>
            </div>

            <div class="space-y-4">
                <div class="space-y-4">
                    @forelse ($this->obligations as $obligation)
                        <article
                            wire:key="obligation-{{ $obligation->id }}"
                            class="tbt-panel overflow-hidden p-5 transition duration-150 hover:border-amber-400/20 sm:p-6"
                        >
                            <div class="flex flex-col gap-4 border-b border-[var(--tbt-border)] pb-5 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="tbt-page-kicker mb-2">{{ $obligation->client->internal_code }}</p>
                                    <h3 class="truncate text-lg font-semibold text-[var(--tbt-text-strong)]">{{ $obligation->client->legal_name }}</h3>
                                    <p class="mt-1 truncate text-sm text-[var(--tbt-text-muted)]">
                                    {{ $obligation->obligation_type }}
                                    </p>
                                </div>
                                <flux:badge class="shrink-0" :color="$obligation->status->badgeColor()">
                                    {{ __('Due date: :state', ['state' => $obligation->status->label()]) }}
                                </flux:badge>
                            </div>

                            <div class="mt-5 grid gap-3 md:grid-cols-3">
                            <div class="rounded-xl border border-[var(--tbt-border)] bg-[var(--tbt-panel-soft)] p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[var(--tbt-muted-strong)]">{{ __('Tax period') }}</p>
                                <p class="mt-2 text-sm font-medium text-[var(--tbt-text-strong)]">{{ $obligation->period_label ?: __('Tax period not recorded') }}</p>
                                <p class="mt-1 text-xs text-amber-300">
                                    {{ __('Date checked on :date', ['date' => $obligation->last_verified_on->format('j M Y')]) }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-[var(--tbt-border)] bg-[var(--tbt-panel-soft)] p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[var(--tbt-muted-strong)]">{{ __('Team target date') }}</p>
                                <time class="mt-2 block text-sm font-medium text-[var(--tbt-text)]" datetime="{{ $obligation->internal_target_date?->toDateString() }}">
                                    {{ $obligation->internal_target_date?->format('j M Y') ?? __('Not set') }}
                                </time>
                            </div>
                            <div class="rounded-xl border border-amber-400/20 bg-amber-400/[0.06] p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-amber-300">{{ __('Filing due date') }}</p>
                                <time class="mt-2 block text-base font-semibold text-[var(--tbt-text-strong)]" datetime="{{ $obligation->effectiveDueDate()->toDateString() }}">
                                    {{ $obligation->effectiveDueDate()->format('j M Y') }}
                                </time>
                                @if (! $obligation->effectiveDueDate()->isSameDay($obligation->statutory_due_date))
                                    <p class="mt-1 text-xs text-amber-300">
                                        {{ __('Original due date: :date', ['date' => $obligation->statutory_due_date->format('j M Y')]) }}
                                    </p>
                                @endif
                            </div>
                            </div>

                            <div class="mt-4 min-w-0 rounded-xl border border-[var(--tbt-border)] bg-[var(--tbt-panel-soft)] p-4">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.08em] text-[var(--tbt-muted-strong)]">
                                    {{ __('Task progress') }}
                                </p>
                                @can('update', $obligation)
                                    <details class="group mt-2 text-left">
                                        <summary class="inline-flex min-h-9 cursor-pointer list-none items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium text-zinc-300 outline-none transition hover:bg-white/[0.06] hover:text-white focus-visible:ring-2 focus-visible:ring-amber-300">
                                            <flux:icon.calendar-days class="size-4" />
                                            {{ __('Change due date') }}
                                            <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
                                        </summary>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="calendar-days"
                                        wire:click="openDeadlineOverride('{{ $obligation->id }}')"
                                    >
                                        {{ __('Edit due date') }}
                                    </flux:button>
                                    @if ($obligation->status === \App\Enums\ObligationStatus::Open)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="archive-box"
                                            wire:click="openDisposition('{{ $obligation->id }}')"
                                        >
                                            {{ __('Cancel or replace deadline') }}
                                        </flux:button>
                                    @endif
                                        </div>
                                    </details>
                                @endcan
                                @if ($obligation->workItem)
                                    @php
                                        $preparer = $obligation->workItem->currentAssignment(\App\Enums\AssignmentRole::Preparer);
                                        $reviewer = $obligation->workItem->currentAssignment(\App\Enums\AssignmentRole::Reviewer);
                                        $manager = $obligation->workItem->currentAssignment(\App\Enums\AssignmentRole::ResponsibleManager);
                                        $activeMembershipId = app(\App\Tenancy\FirmContext::class)->membership()?->id;
                                        $canTransition = $obligation->workItem->genericTransitionTargetsFor($activeMembershipId) !== [];
                                        $checklistTotal = $obligation->workItem->checklist?->version?->items?->count() ?? 0;
                                        $checklistCompleted = $obligation->workItem->checklist?->completions?->count() ?? 0;
                                    @endphp
                                    <flux:badge :color="$obligation->workItem->status->badgeColor()">
                                        {{ __('Task: :state', ['state' => $obligation->workItem->status->label()]) }}
                                    </flux:badge>
                                    <flux:badge class="ms-1" :color="$obligation->workItem->risk_status->badgeColor()">
                                        {{ __('Attention: :level', ['level' => $obligation->workItem->risk_status->label()]) }}
                                    </flux:badge>
                                    @if ($obligation->filingRecord)
                                        <flux:badge class="ms-1" :color="$obligation->filingRecord->status->badgeColor()">
                                            {{ __('Filing: :state', ['state' => $obligation->filingRecord->status->label()]) }}
                                        </flux:badge>
                                    @endif
                                    @if ($obligation->paymentRecord)
                                        <flux:badge class="ms-1" :color="$obligation->paymentRecord->status->badgeColor()">
                                            {{ __('Payment: :state', ['state' => $obligation->paymentRecord->status->label()]) }}
                                        </flux:badge>
                                    @endif
                                    @if ($obligation->taxRecord)
                                        <flux:badge class="ms-1" :color="$obligation->taxRecord->status->badgeColor()">
                                            {{ __('Tax: :type :state', ['type' => $obligation->taxRecord->tax_type->label(), 'state' => $obligation->taxRecord->status->label()]) }}
                                        </flux:badge>
                                    @endif
                                    <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-3">
                                        <div><dt class="text-zinc-500">{{ __('Prepared by') }}</dt><dd class="mt-1 truncate text-zinc-300">{{ $preparer?->assignedMembership?->user?->name ?? __('Not assigned') }}</dd></div>
                                        <div><dt class="text-zinc-500">{{ __('Reviewed by') }}</dt><dd class="mt-1 truncate text-zinc-300">{{ $reviewer?->assignedMembership?->user?->name ?? __('Not assigned') }}</dd></div>
                                        <div><dt class="text-zinc-500">{{ __('Responsible manager') }}</dt><dd class="mt-1 truncate text-zinc-300">{{ $manager?->assignedMembership?->user?->name ?? __('Not assigned') }}</dd></div>
                                    </dl>
                                    @if ($obligation->workItem->checklist)
                                        <p class="mt-2 text-xs text-zinc-400">
                                            {{ __('Checklist: :completed of :total completed', [
                                                'completed' => $checklistCompleted,
                                                'total' => $checklistTotal,
                                            ]) }}
                                        </p>
                                    @endif
                                    @if ($obligation->workItem->followUps->isNotEmpty())
                                        <p class="mt-1 text-xs text-amber-300">
                                            {{ trans_choice(
                                                '{1} :count linked follow-up (:state)|[2,*] :count linked follow-ups (latest :state)',
                                                $obligation->workItem->followUps->count(),
                                                [
                                                    'count' => $obligation->workItem->followUps->count(),
                                                    'state' => $obligation->workItem->followUps->last()->status->label(),
                                                ],
                                            ) }}
                                        </p>
                                    @endif
                                    <details class="group mt-2 text-left">
                                        <summary class="inline-flex min-h-9 cursor-pointer list-none items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium text-zinc-300 outline-none transition hover:bg-white/[0.06] hover:text-white focus-visible:ring-2 focus-visible:ring-amber-300">
                                            <flux:icon.wrench-screwdriver class="size-4" />
                                            {{ __('Update task') }}
                                            <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
                                        </summary>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                    @if ($obligation->workItem->checklist)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="clipboard-document-check"
                                            wire:click="openChecklist('{{ $obligation->workItem->id }}')"
                                        >
                                            {{ __('Open checklist') }}
                                        </flux:button>
                                    @endif
                                    @if ($obligation->workItem->status === \App\Enums\WorkItemStatus::UnderReview && Gate::allows('review', $obligation->workItem) && $reviewer?->assigned_membership_id === $activeMembershipId)
                                        <flux:button
                                            size="sm"
                                            variant="filled"
                                            icon="check-badge"
                                            wire:click="openReview('{{ $obligation->workItem->id }}')"
                                        >
                                            {{ __('Decide review') }}
                                        </flux:button>
                                    @endif
                                    @if ($canTransition)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="arrow-path"
                                            wire:click="openTransition('{{ $obligation->workItem->id }}')"
                                        >
                                            {{ __('Change task status') }}
                                        </flux:button>
                                    @endif
                                    @can('create', \App\Models\WorkItem::class)
                                        @if ($obligation->workItem->status === \App\Enums\WorkItemStatus::Completed && ! $obligation->workItem->isFollowUp())
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-uturn-left"
                                                wire:click="openReopen('{{ $obligation->workItem->id }}')"
                                            >
                                                {{ __('Reopen as follow-up') }}
                                            </flux:button>
                                        @endif
                                    @endcan
                                    @can('update', $obligation->workItem)
                                        @if (! in_array($obligation->workItem->status, [\App\Enums\WorkItemStatus::Completed, \App\Enums\WorkItemStatus::Cancelled], true))
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="users"
                                                wire:click="openReassignment('{{ $obligation->workItem->id }}')"
                                            >
                                                {{ __('Assign team') }}
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-up-circle"
                                                wire:click="openMigration('{{ $obligation->workItem->id }}')"
                                            >
                                                {{ __('Use latest checklist') }}
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="exclamation-triangle"
                                                wire:click="openRisk('{{ $obligation->workItem->id }}')"
                                            >
                                                {{ __('Set attention level') }}
                                            </flux:button>
                                        @endif
                                    @endcan
                                    @if ($obligation->filingRecord ? Gate::allows('transition', $obligation->filingRecord) : Gate::allows('create', \App\Models\FilingRecord::class))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="document-check"
                                            wire:click="openFiling('{{ $obligation->id }}')"
                                        >
                                            {{ $obligation->filingRecord ? __('Update filing status') : __('Record filing status') }}
                                        </flux:button>
                                    @endif
                                    @if ($obligation->paymentRecord ? Gate::allows('transition', $obligation->paymentRecord) : Gate::allows('create', \App\Models\PaymentRecord::class))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="banknotes"
                                            wire:click="openPayment('{{ $obligation->id }}')"
                                        >
                                            {{ $obligation->paymentRecord ? __('Update payment status') : __('Record payment status') }}
                                        </flux:button>
                                    @endif
                                    @if ($obligation->taxRecord ? Gate::allows('amend', $obligation->taxRecord) : Gate::allows('create', \App\Models\TaxRecord::class))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="calculator"
                                            wire:click="openTax('{{ $obligation->id }}')"
                                        >
                                            {{ $obligation->taxRecord ? __('Update tax details') : __('Record tax details') }}
                                        </flux:button>
                                    @endif
                                        </div>
                                    </details>
                                @else
                                    @can('create', \App\Models\WorkItem::class)
                                    <flux:button
                                        size="sm"
                                        variant="filled"
                                        icon="user-plus"
                                        wire:click="openAssignment('{{ $obligation->id }}')"
                                    >
                                        {{ __('Assign team') }}
                                    </flux:button>
                                    @endcan
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-14 text-center">
                            <p class="text-sm font-medium text-zinc-200">
                                {{ $search === '' ? __('No due dates recorded') : __('No deadlines match this search') }}
                            </p>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">
                                {{ $search === ''
                                    ? __('Select an active client and add the first filing or renewal due date.')
                                    : __('Check the client, deadline type or tax period and try again.') }}
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($this->obligations->hasPages())
                <div class="mt-6">
                    {{ $this->obligations->links() }}
                </div>
            @endif
        </section>

        @can('create', \App\Models\Obligation::class)
        <aside aria-labelledby="create-obligation-heading" class="min-[1900px]:sticky min-[1900px]:top-8 min-[1900px]:self-start">
            <div class="tbt-panel p-6">
                <div class="mb-6">
                    <span class="mb-4 grid size-10 place-items-center rounded-xl bg-amber-400 text-black">
                        <flux:icon.calendar-days class="size-5" />
                    </span>
                    <h2 id="create-obligation-heading" class="text-lg font-semibold text-zinc-100">{{ __('Add a due date') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        {{ __('Enter only a date checked by an authorised person. The note showing where it was checked remains part of the record.') }}
                    </p>
                </div>

                @if ($this->clients->isEmpty())
                    <flux:callout
                        variant="warning"
                        icon="exclamation-triangle"
                        :heading="__('An active client is required')"
                    >
                        {{ __('Ask a firm administrator to add or reactivate a client before recording a deadline.') }}
                    </flux:callout>
                @else
                    <form wire:submit="createObligation" class="space-y-5">
                        <flux:select wire:model="clientId" :label="__('Client')" required>
                            <flux:select.option value="">{{ __('Select an active client') }}</flux:select.option>
                            @foreach ($this->clients as $client)
                                <flux:select.option :value="$client->id">
                                    {{ $client->internal_code }} · {{ $client->legal_name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input
                            wire:model="obligationType"
                            :label="__('Deadline type')"
                            placeholder="Manual VAT review"
                            maxlength="100"
                            required
                        />

                        <flux:input
                            wire:model="periodLabel"
                            :label="__('Period label')"
                            :description="__('Optional. Use the actual assigned or reviewed period label.')"
                            placeholder="Q2 2026"
                            maxlength="100"
                        />

                        <div class="grid gap-5 sm:grid-cols-2 2xl:grid-cols-1">
                            <flux:input
                                wire:model="statutoryDueDate"
                                type="date"
                                :label="__('FTA or legal due date')"
                                required
                            />

                            <flux:input
                                wire:model="internalTargetDate"
                                type="date"
                                :label="__('Team target date')"
                                :description="__('Optional. Set an earlier date for your team to finish the work before the legal due date.')"
                            />
                        </div>

                        <flux:input
                            wire:model="lastVerifiedOn"
                            type="date"
                            :label="__('Date checked')"
                            max="{{ now('Asia/Dubai')->toDateString() }}"
                            required
                        />

                        <flux:textarea
                            wire:model="sourceReference"
                            :label="__('Where was this date checked?')"
                            :description="__('Required. Record where the entered date was checked. Do not include credentials or private documents.')"
                            rows="3"
                            maxlength="1000"
                            required
                        />

                        <flux:button
                            type="submit"
                            variant="primary"
                            class="w-full"
                            wire:loading.attr="disabled"
                            wire:target="createObligation"
                        >
                            <span wire:loading.remove wire:target="createObligation">{{ __('Add due date') }}</span>
                            <span wire:loading wire:target="createObligation">{{ __('Adding due date...') }}</span>
                        </flux:button>
                    </form>
                @endif
            </div>

            <p class="mt-4 px-1 text-xs leading-5 text-zinc-600">
                {{ __('Adding a due date does not assign the task. Assign it separately when the team is ready to start.') }}
            </p>
        </aside>
        @endcan
    </div>

    <flux:modal
        name="obligation-disposition"
        wire:model="showDispositionModal"
        @close="closeDispositionModal"
        class="max-w-lg"
    >
        <form wire:submit="disposeObligation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel or replace deadline') }}</flux:heading>
                <flux:text class="mt-2">{{ $dispositionLabel }}</flux:text>
                <flux:text class="mt-2 text-zinc-500">{{ __('The old deadline remains in the history. Choose a replacement only when another open deadline should take its place.') }}</flux:text>
            </div>
            <flux:select wire:model.live="dispositionStatus" :label="__('What should happen to this deadline?')" required>
                <flux:select.option value="">{{ __('Select what should happen') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                <flux:select.option value="superseded">{{ __('Replace with another deadline') }}</flux:select.option>
            </flux:select>
            @if ($dispositionStatus === 'superseded')
                <flux:select wire:model="replacementObligationId" :label="__('Replacement deadline')" required>
                    <flux:select.option value="">{{ __('Select replacement') }}</flux:select.option>
                    @foreach ($this->replacementObligations as $replacement)
                        <flux:select.option :value="$replacement->id">
                            {{ $replacement->client->internal_code }} · {{ $replacement->obligation_type }} · {{ $replacement->effectiveDueDate()->format('j M Y') }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:textarea wire:model="dispositionReason" :label="__('Reason')" rows="4" maxlength="500" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeDispositionModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save deadline change') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="deadline-override"
        wire:model="showDeadlineOverrideModal"
        @close="closeDeadlineOverrideModal"
        class="max-w-lg"
    >
        <form wire:submit="overrideDeadline" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Change due date') }}</flux:heading>
                <flux:text class="mt-2">{{ $deadlineOverrideLabel }}</flux:text>
                <flux:text class="mt-2 text-zinc-500">
                    {{ __('The original due date remains in the history. The new date will be used on dashboards, lists and reminders.') }}
                </flux:text>
            </div>
            <flux:input wire:model="deadlineOverrideDate" type="date" :label="__('New due date')" required />
            <flux:textarea wire:model="deadlineOverrideReason" :label="__('Reason')" rows="4" maxlength="500" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeDeadlineOverrideModal">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Record override') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="assign-primary-work"
        wire:model="showAssignmentModal"
        @close="closeAssignmentModal"
        class="max-w-xl"
    >
        <form wire:submit="assignWork" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Assign primary work') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Create a client task for :obligation and assign the people who will prepare, review and manage it.', [
                        'obligation' => $selectedObligationLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="workItem" />
            <flux:error name="assignments" />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="preparerMembershipId" :label="__('Preparer')" required>
                    <flux:select.option value="">{{ __('Select preparer') }}</flux:select.option>
                    @foreach ($this->preparers as $membership)
                        <flux:select.option :value="$membership->id">{{ $membership->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="reviewerMembershipId" :label="__('Reviewer')" required>
                    <flux:select.option value="">{{ __('Select reviewer') }}</flux:select.option>
                    @foreach ($this->reviewers as $membership)
                        <flux:select.option :value="$membership->id">{{ $membership->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:select wire:model="managerMembershipId" :label="__('Responsible manager')" required>
                <flux:select.option value="">{{ __('Select responsible manager') }}</flux:select.option>
                @foreach ($this->managers as $membership)
                    <flux:select.option :value="$membership->id">{{ $membership->user->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="assignmentReason"
                :label="__('Assignment reason')"
                :description="__('Required. This reason is saved with the initial team assignments.')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeAssignmentModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="assignWork">
                    <span wire:loading.remove wire:target="assignWork">{{ __('Create and assign work') }}</span>
                    <span wire:loading wire:target="assignWork">{{ __('Assigning work...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="transition-work-item"
        wire:model="showTransitionModal"
        @close="closeTransitionModal"
        class="max-w-lg"
    >
        <form wire:submit="transitionWork" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Change task status') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Change :work from :status to the next available task status. The reason and previous status are saved.', [
                        'work' => $selectedWorkItemLabel,
                        'status' => \App\Enums\WorkItemStatus::tryFrom($selectedWorkItemStatus)?->label() ?? __('the current state'),
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="targetStatus" />

            @if ($this->transitionOptions === [])
                <flux:callout
                    variant="warning"
                    icon="lock-closed"
                    :heading="__('No status change is available')"
                >
                    {{ __('The current state is terminal or the next action belongs to another assigned role. Close this window and ask the responsible assignee to continue.') }}
                </flux:callout>
            @else
                <flux:select wire:model.live="targetWorkItemStatus" :label="__('Next task status')" required>
                    <flux:select.option value="">{{ __('Select an allowed status') }}</flux:select.option>
                    @foreach ($this->transitionOptions as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($targetWorkItemStatus === \App\Enums\WorkItemStatus::UnderReview->value)
                    @if ($selectedRequiredChecklistCompleted === $selectedRequiredChecklistTotal)
                        <flux:callout
                            variant="success"
                            icon="check-circle"
                            :heading="__('Review evidence ready')"
                        >
                            {{ trans_choice(
                                '{0} This checklist has no required items.|{1} The required checklist item is completed.|[2,*] All :count required checklist items are completed.',
                                $selectedRequiredChecklistTotal,
                                ['count' => $selectedRequiredChecklistTotal],
                            ) }}
                        </flux:callout>
                    @else
                        <flux:callout
                            variant="warning"
                            icon="clipboard-document-check"
                            :heading="__('Checklist evidence required')"
                        >
                            {{ __(':completed of :total required items are completed. Close this window, open the checklist and complete the remaining items before submitting for review.', [
                                'completed' => $selectedRequiredChecklistCompleted,
                                'total' => $selectedRequiredChecklistTotal,
                            ]) }}
                        </flux:callout>
                    @endif
                @endif

                <flux:textarea
                    wire:model="transitionReason"
                    :label="__('Reason for status change')"
                    :description="__('Briefly explain the change. Do not include passwords or private document numbers.')"
                    rows="3"
                    maxlength="500"
                    required
                />
            @endif

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeTransitionModal">
                    {{ __('Cancel') }}
                </flux:button>
                @if ($this->transitionOptions !== [])
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="transitionWork">
                        <span wire:loading.remove wire:target="transitionWork">{{ __('Record status change') }}</span>
                        <span wire:loading wire:target="transitionWork">{{ __('Recording change...') }}</span>
                    </flux:button>
                @endif
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="decide-work-item-review"
        wire:model="showReviewModal"
        @close="closeReviewModal"
        class="max-w-lg"
    >
        <form wire:submit="decideReview" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Decide review') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Record the reviewer decision for :work. Approval moves work to awaiting client approval. Returning sends it back to the assigned preparer for changes.', [
                        'work' => $reviewWorkItemLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="decision" />
            <flux:error name="reviewDecision" />

            <flux:select wire:model="reviewDecision" :label="__('Decision')" required>
                <flux:select.option value="">{{ __('Select a decision') }}</flux:select.option>
                @foreach (\App\Enums\ReviewDecision::cases() as $decision)
                    <flux:select.option :value="$decision->value">{{ $decision->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="reviewReason"
                :label="__('Review reason')"
                :description="__('Required. Briefly explain the change. Do not include passwords or private document numbers.')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeReviewModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="decideReview">
                    <span wire:loading.remove wire:target="decideReview">{{ __('Record decision') }}</span>
                    <span wire:loading wire:target="decideReview">{{ __('Recording decision...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="work-item-checklist"
        wire:model="showChecklistModal"
        @close="closeChecklistModal"
        class="max-w-2xl"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Work checklist') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Review the checklist for :work. Completed items keep their notes and cannot be reopened.', [
                        'work' => $checklistWorkItemLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="checklistItem" />

            @if ($this->selectedChecklist)
                @php
                    $completedByItem = $this->selectedChecklist->completions->keyBy('checklist_item_id');
                    $incompleteItems = $this->selectedChecklist->version->items
                        ->reject(fn (\App\Models\ChecklistItem $item): bool => $completedByItem->has($item->id));
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/8 pb-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-200">
                            {{ $this->selectedChecklist->version->template->name }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ __('Published version :version', ['version' => $this->selectedChecklist->version->version]) }}
                        </p>
                    </div>
                    <flux:badge color="zinc">
                        {{ __(':completed of :total complete', [
                            'completed' => $completedByItem->count(),
                            'total' => $this->selectedChecklist->version->items->count(),
                        ]) }}
                    </flux:badge>
                </div>

                <div class="divide-y divide-white/8">
                    @foreach ($this->selectedChecklist->version->items as $item)
                        @php($completion = $completedByItem->get($item->id))
                        <div wire:key="checklist-item-{{ $item->id }}" class="flex gap-3 py-4">
                            <span @class([
                                'mt-0.5 grid size-6 shrink-0 place-items-center rounded-full',
                                'bg-emerald-400 text-black' => $completion,
                                'bg-zinc-800 text-zinc-500' => ! $completion,
                            ])>
                                @if ($completion)
                                    <flux:icon.check class="size-4" />
                                @else
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-zinc-200">{{ $item->label }}</p>
                                @if ($completion)
                                    <p class="mt-1 text-sm leading-6 text-zinc-400">{{ $completion->evidence_note }}</p>
                                    <p class="mt-1 text-xs text-zinc-600">
                                        {{ __('Completed :date', ['date' => $completion->completed_at->timezone(app(\App\Tenancy\FirmContext::class)->firm()->timezone)->format('j M Y, H:i')]) }}
                                    </p>
                                @elseif ($item->required)
                                    <p class="mt-1 text-xs text-amber-300">{{ __('Required') }}</p>
                                @else
                                    <p class="mt-1 text-xs text-zinc-500">{{ __('Optional') }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($this->canCompleteChecklist && $incompleteItems->isNotEmpty())
                    <form wire:submit="completeChecklistItem" class="space-y-5 border-t border-white/8 pt-5">
                        <div>
                            <p class="text-sm font-medium text-zinc-200">{{ __('Complete a checklist item') }}</p>
                            <p class="mt-1 text-sm leading-6 text-zinc-500">
                                {{ __('Select one item and add a short completion note. Completed items cannot be edited or removed.') }}
                            </p>
                        </div>
                        <flux:select wire:model="checklistItemId" :label="__('Checklist item')" required>
                            <flux:select.option value="">{{ __('Select an incomplete item') }}</flux:select.option>
                            @foreach ($incompleteItems as $item)
                                <flux:select.option :value="$item->id">{{ $item->label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:textarea
                            wire:model="checklistEvidenceNote"
                            :label="__('Completion note')"
                            :description="__('Required. Do not include credentials or private documents.')"
                            rows="3"
                            maxlength="500"
                            required
                        />
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="completeChecklistItem">
                                <span wire:loading.remove wire:target="completeChecklistItem">{{ __('Complete selected item') }}</span>
                                <span wire:loading wire:target="completeChecklistItem">{{ __('Retaining evidence...') }}</span>
                            </flux:button>
                        </div>
                    </form>
                @elseif ($incompleteItems->isEmpty())
                    <flux:callout variant="success" icon="check-circle" :heading="__('Checklist complete')">
                        {{ __('Every item in this checklist is completed.') }}
                    </flux:callout>
                @endif
            @endif

            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" wire:click="closeChecklistModal">
                    {{ __('Close') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="reassign-work-item"
        wire:model="showReassignmentModal"
        @close="closeReassignmentModal"
        class="max-w-lg"
    >
        <form wire:submit="reassignWork" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reassign work ownership') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Change one owner for :work. The former assignment remains in history and the new assignment becomes effective immediately.', [
                        'work' => $reassignmentWorkItemLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="reassignment" />

            <div class="tbt-panel divide-y divide-[var(--tbt-border)] px-5">
                @foreach (\App\Enums\AssignmentRole::cases() as $role)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <span class="text-sm text-zinc-500">{{ $role->label() }}</span>
                        <span class="truncate text-sm font-medium text-zinc-200">
                            {{ $currentOwnerNames[$role->value] ?? __('Not assigned') }}
                        </span>
                    </div>
                @endforeach
            </div>

            <flux:select wire:model.live="reassignmentRole" :label="__('Assignment role')" required>
                <flux:select.option value="">{{ __('Select role to reassign') }}</flux:select.option>
                @foreach (\App\Enums\AssignmentRole::cases() as $role)
                    <flux:select.option :value="$role->value">{{ $role->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model="replacementMembershipId"
                :label="__('Replacement member')"
                :disabled="$reassignmentRole === ''"
                required
            >
                <flux:select.option value="">
                    {{ $reassignmentRole === '' ? __('Select a role first') : __('Select an active eligible member') }}
                </flux:select.option>
                @foreach ($this->reassignmentCandidates as $membership)
                    <flux:select.option :value="$membership->id">
                        {{ $membership->user->name }} · {{ $membership->role->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="reassignmentReason"
                :label="__('Reassignment reason')"
                :description="__('Required. Record why ownership changed. Do not include credentials or private documents.')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeReassignmentModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="reassignWork">
                    <span wire:loading.remove wire:target="reassignWork">{{ __('Record reassignment') }}</span>
                    <span wire:loading wire:target="reassignWork">{{ __('Recording reassignment...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="record-filing-state"
        wire:model="showFilingModal"
        @close="closeFilingModal"
        class="max-w-lg"
    >
        <form wire:submit="saveFiling" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $filingRecordId === '' ? __('Open filing record') : __('Update filing state') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ __('Record the filing state for :work. Filing state is stored separately from work status and payment status, and this platform does not transmit anything to any authority.', [
                        'work' => $filingWorkLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="status" />
            <flux:error name="targetStatus" />
            <flux:error name="filingStatus" />

            @if ($this->filingStatusOptions === [])
                <flux:callout
                    variant="warning"
                    icon="lock-closed"
                    :heading="__('No filing move is available')"
                >
                    {{ __('This filing state has no further recorded transition. Close this window.') }}
                </flux:callout>
            @else
                <flux:select wire:model.live="filingStatus" :label="__('Filing state')" required>
                    <flux:select.option value="">{{ __('Select a filing state') }}</flux:select.option>
                    @foreach ($this->filingStatusOptions as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($filingRecordId !== '' && \App\Enums\FilingStatus::tryFrom($filingStatus)?->requiresFilingReference())
                    <flux:error name="filingReference" />
                    <flux:input
                        wire:model="filingReference"
                        :label="__('Filing reference')"
                        :description="__('Required. The reference issued by the authority. Do not record credentials.')"
                        maxlength="100"
                    />

                    @if ($filingStatus === \App\Enums\FilingStatus::Filed->value)
                        <flux:error name="filedOn" />
                        <flux:input
                            type="date"
                            wire:model="filingFiledOn"
                            :label="__('Filed on')"
                            :description="__('Required. The date the return was filed.')"
                        />
                    @endif
                @endif

                <flux:textarea
                    wire:model="filingReason"
                    :label="__('Filing reason')"
                    :description="__('Required. Briefly explain the change. Do not include passwords or private document numbers.')"
                    rows="3"
                    maxlength="500"
                    required
                />
            @endif

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeFilingModal">
                    {{ __('Cancel') }}
                </flux:button>
                @if ($this->filingStatusOptions !== [])
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveFiling">
                        <span wire:loading.remove wire:target="saveFiling">{{ __('Record filing state') }}</span>
                        <span wire:loading wire:target="saveFiling">{{ __('Recording filing state...') }}</span>
                    </flux:button>
                @endif
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="record-payment-state"
        wire:model="showPaymentModal"
        @close="closePaymentModal"
        class="max-w-lg"
    >
        <form wire:submit="savePayment" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $paymentRecordId === '' ? __('Open payment record') : __('Update payment state') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ __('Record the payment state for :work. Payment state is stored separately from work status and filing status. This platform records an observed settlement and never initiates or authorises any transfer.', [
                        'work' => $paymentWorkLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="status" />
            <flux:error name="targetStatus" />
            <flux:error name="paymentStatus" />

            @if ($this->paymentStatusOptions === [])
                <flux:callout
                    variant="warning"
                    icon="lock-closed"
                    :heading="__('No payment move is available')"
                >
                    {{ __('This payment state is settled and has no further recorded transition. Close this window.') }}
                </flux:callout>
            @else
                <flux:select wire:model.live="paymentStatus" :label="__('Payment state')" required>
                    <flux:select.option value="">{{ __('Select a payment state') }}</flux:select.option>
                    @foreach ($this->paymentStatusOptions as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($paymentRecordId !== '' && \App\Enums\PaymentStatus::tryFrom($paymentStatus)?->requiresPaymentEvidence())
                    <flux:error name="paymentReference" />
                    <flux:input
                        wire:model="paymentReference"
                        :label="__('Payment reference')"
                        :description="__('Required. The reference from the settlement evidence. Do not record card or bank credentials.')"
                        maxlength="100"
                    />

                    <flux:error name="paidOn" />
                    <flux:input
                        type="date"
                        wire:model="paymentPaidOn"
                        :label="__('Paid on')"
                        :description="__('Required. The date the payment settled.')"
                    />
                @endif

                <flux:textarea
                    wire:model="paymentReason"
                    :label="__('Payment reason')"
                    :description="__('Required. Briefly explain the change. Do not include passwords or private document numbers.')"
                    rows="3"
                    maxlength="500"
                    required
                />
            @endif

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closePaymentModal">
                    {{ __('Cancel') }}
                </flux:button>
                @if ($this->paymentStatusOptions !== [])
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="savePayment">
                        <span wire:loading.remove wire:target="savePayment">{{ __('Record payment state') }}</span>
                        <span wire:loading wire:target="savePayment">{{ __('Recording payment state...') }}</span>
                    </flux:button>
                @endif
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="reopen-work-item"
        wire:model="showReopenModal"
        @close="closeReopenModal"
        class="max-w-lg"
    >
        <form wire:submit="reopenWork" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reopen as follow-up') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Create linked follow-up work for :work. The completed original, its status history, checklist evidence and assignment history stay exactly as they are. The follow-up starts its own lifecycle on the latest published workflow and checklist.', [
                        'work' => $reopenWorkItemLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="reopen" />

            <flux:textarea
                wire:model="reopenReason"
                :label="__('Reopen reason')"
                :description="__('Required. Record why the completed work needs correction. Do not include credentials or private documents.')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeReopenModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="reopenWork">
                    <span wire:loading.remove wire:target="reopenWork">{{ __('Create follow-up work') }}</span>
                    <span wire:loading wire:target="reopenWork">{{ __('Creating follow-up...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="set-work-item-risk-status"
        wire:model="showRiskModal"
        @close="closeRiskModal"
        class="max-w-lg"
    >
        <form wire:submit="saveRisk" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Set attention level') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Set how urgently :work needs attention. This does not change the filing, payment or tax status.', [
                        'work' => $riskWorkItemLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="riskLevel" />

            <flux:select wire:model="riskLevel" :label="__('Attention level')" required>
                @foreach (\App\Enums\RiskLevel::cases() as $level)
                    <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="riskReason"
                :label="__('Reason')"
                :description="__('Required. Briefly explain the change. Do not include passwords or private document numbers.')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeRiskModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveRisk">
                    <span wire:loading.remove wire:target="saveRisk">{{ __('Save attention level') }}</span>
                    <span wire:loading wire:target="saveRisk">{{ __('Saving attention level...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="record-tax-figures"
        wire:model="showTaxModal"
        @close="closeTaxModal"
        class="max-w-lg"
    >
        <form wire:submit="saveTax" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $taxRecordId === '' ? __('Open tax record') : __('Amend tax record') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ __('Record the tax figures for :work. Enter values prepared outside this platform. The platform does not calculate the tax amount.', [
                        'work' => $taxWorkLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="taxType" />
            <flux:error name="targetStatus" />

            <flux:select wire:model="taxType" :label="__('Tax type')" :disabled="$taxRecordId !== ''" required>
                @foreach (\App\Enums\TaxType::cases() as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="taxPeriodLabel"
                :label="__('Period')"
                :readonly="$taxRecordId !== ''"
                maxlength="100"
                required
            />

            <div class="grid grid-cols-3 gap-3">
                <flux:input wire:model="taxCurrency" :label="__('Currency')" maxlength="3" :readonly="$taxRecordId !== ''" required />
                <flux:input wire:model="taxTaxableAmount" :label="__('Taxable amount')" inputmode="decimal" required />
                <flux:input wire:model="taxTaxAmount" :label="__('Tax amount')" inputmode="decimal" required />
            </div>

            @if ($taxRecordId !== '')
                <flux:select wire:model="taxTargetStatus" :label="__('Status')" required>
                    @foreach (\App\Enums\TaxRecordStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:text class="text-xs text-zinc-500">
                    {{ __('Marking a record final locks it against further amendment.') }}
                </flux:text>
            @endif

            <flux:textarea
                wire:model="taxReason"
                :label="__('Reason')"
                :description="__('Required. Briefly explain the change. Do not include passwords or private document numbers.')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeTaxModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveTax">
                    <span wire:loading.remove wire:target="saveTax">{{ __('Record tax figures') }}</span>
                    <span wire:loading wire:target="saveTax">{{ __('Recording tax figures...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="migrate-work-item-workflow-version"
        wire:model="showMigrationModal"
        @close="closeMigrationModal"
        class="max-w-lg"
    >
        <form wire:submit="migrateWorkflowVersion" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Use a newer checklist') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Move :work to a newer task checklist. Existing status, assignments and completed checklist notes remain in the history.', [
                        'work' => $migrationWorkItemLabel,
                        'version' => $migrationCurrentVersion,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="migrationTargetDefinitionId" />

            @if ($this->availableWorkflowVersions->isEmpty())
                <flux:callout
                    variant="warning"
                    icon="lock-closed"
                    :heading="__('No later version is published')"
                >
                    {{ __('This task already uses the latest checklist.') }}
                </flux:callout>
            @else
                <flux:select wire:model="migrationTargetDefinitionId" :label="__('New checklist version')" required>
                    <flux:select.option value="">{{ __('Select a later published version') }}</flux:select.option>
                    @foreach ($this->availableWorkflowVersions as $definition)
                        <flux:select.option :value="$definition->id">
                            {{ __('Version :version', ['version' => $definition->version]) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    wire:model="migrationReason"
                    :label="__('Reason for changing the checklist')"
                    :description="__('Required. Briefly explain the change. Do not include passwords or private document numbers.')"
                    rows="3"
                    maxlength="500"
                    required
                />
            @endif

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeMigrationModal">
                    {{ __('Cancel') }}
                </flux:button>
                @if ($this->availableWorkflowVersions->isNotEmpty())
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="migrateWorkflowVersion">
                        <span wire:loading.remove wire:target="migrateWorkflowVersion">{{ __('Use selected checklist') }}</span>
                        <span wire:loading wire:target="migrateWorkflowVersion">{{ __('Updating checklist...') }}</span>
                    </flux:button>
                @endif
            </div>
        </form>
    </flux:modal>
</div>
