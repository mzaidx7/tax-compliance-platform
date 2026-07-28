{{--
THESIS: A due date is accepted only when its client, origin, verification and internal target stay visible together.
OWN-WORLD: Matte ink ledger rows, silver date hierarchy and restrained gold for controlled work actions.
STORY: A team member sees only relevant work, advances an allowed state and leaves durable evidence.
FIRST VIEWPORT: The chronological register owns the wide field while authorised managers retain the manual-entry station.
FORM: Deadline review rail, grounded structure seven, seed 75cba0e2.
--}}
<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="mb-3 text-sm font-medium text-amber-300">{{ $this->currentFirmName }}</p>
                <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                    {{ __('Manual obligations') }}
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-zinc-400">
                    {{ __('Review due dates, ownership and controlled work progress without blending deadline, filing or payment state.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:badge color="amber" icon="pencil-square">{{ __('Human entered') }}</flux:badge>
                <flux:badge color="zinc" icon="shield-check">{{ __('Role controlled') }}</flux:badge>
            </div>
        </div>
    </header>

    <div @class([
        'mt-9 grid gap-10',
        'xl:grid-cols-[minmax(0,1fr)_23rem]' => Gate::allows('create', \App\Models\Obligation::class),
    ])>
        <section aria-labelledby="obligation-register-heading">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="obligation-register-heading" class="text-lg font-semibold text-zinc-100">{{ __('Deadline register') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Work, filing and payment states will be tracked separately.') }}</p>
                </div>
                <div class="w-full sm:max-w-xs">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        :label="__('Search obligations')"
                        placeholder="Client, type or period"
                        icon="magnifying-glass"
                    />
                </div>
            </div>

            <div class="overflow-hidden border-y border-white/8">
                <div class="hidden grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_8rem_8rem_9rem] gap-4 border-b border-white/8 px-4 py-3 text-xs font-medium text-zinc-500 lg:grid">
                    <span>{{ __('Client and obligation') }}</span>
                    <span>{{ __('Period and origin') }}</span>
                    <span>{{ __('Internal target') }}</span>
                    <span>{{ __('Effective due') }}</span>
                    <span class="text-right">{{ __('Work ownership') }}</span>
                </div>

                <div class="divide-y divide-white/8">
                    @forelse ($this->obligations as $obligation)
                        <article
                            wire:key="obligation-{{ $obligation->id }}"
                            class="grid gap-4 px-4 py-5 transition-colors duration-150 hover:bg-white/[0.025] lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_8rem_8rem_9rem] lg:items-center"
                        >
                            <div class="min-w-0">
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Client and obligation') }}</span>
                                <p class="truncate text-sm font-medium text-zinc-100">{{ $obligation->client->legal_name }}</p>
                                <p class="mt-1 truncate text-sm text-zinc-400">
                                    {{ $obligation->client->internal_code }} · {{ $obligation->obligation_type }}
                                </p>
                            </div>
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Period and origin') }}</span>
                                <p class="text-sm text-zinc-300">{{ $obligation->period_label ?: __('No period label') }}</p>
                                <p class="mt-1 text-xs text-amber-300">
                                    {{ $obligation->origin->label() }} · {{ __('Verified :date', ['date' => $obligation->last_verified_on->format('j M Y')]) }}
                                </p>
                            </div>
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Internal target') }}</span>
                                <time class="text-sm text-zinc-400" datetime="{{ $obligation->internal_target_date?->toDateString() }}">
                                    {{ $obligation->internal_target_date?->format('j M Y') ?? __('Not set') }}
                                </time>
                            </div>
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Effective due') }}</span>
                                <time class="text-sm font-medium text-zinc-100" datetime="{{ $obligation->effectiveDueDate()->toDateString() }}">
                                    {{ $obligation->effectiveDueDate()->format('j M Y') }}
                                </time>
                                @if (! $obligation->effectiveDueDate()->isSameDay($obligation->statutory_due_date))
                                    <p class="mt-1 text-xs text-amber-300">
                                        {{ __('Statutory :date', ['date' => $obligation->statutory_due_date->format('j M Y')]) }}
                                    </p>
                                @endif
                            </div>
                            <div class="lg:text-right">
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Work ownership') }}</span>
                                <div class="mb-2 flex flex-wrap gap-1.5 lg:justify-end">
                                    <flux:badge :color="$obligation->status->badgeColor()">
                                        {{ __('Deadline: :state', ['state' => $obligation->status->label()]) }}
                                    </flux:badge>
                                </div>
                                @can('update', $obligation)
                                    <flux:button
                                        class="mb-2"
                                        size="sm"
                                        variant="ghost"
                                        icon="calendar-days"
                                        wire:click="openDeadlineOverride('{{ $obligation->id }}')"
                                    >
                                        {{ __('Override deadline') }}
                                    </flux:button>
                                    @if ($obligation->status === \App\Enums\ObligationStatus::Open)
                                        <flux:button
                                            class="mb-2"
                                            size="sm"
                                            variant="ghost"
                                            icon="archive-box"
                                            wire:click="openDisposition('{{ $obligation->id }}')"
                                        >
                                            {{ __('Cancel or supersede') }}
                                        </flux:button>
                                    @endif
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
                                        {{ __('Work: :state', ['state' => $obligation->workItem->status->label()]) }}
                                    </flux:badge>
                                    <flux:badge class="ms-1" :color="$obligation->workItem->risk_status->badgeColor()">
                                        {{ __('Risk: :level', ['level' => $obligation->workItem->risk_status->label()]) }}
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
                                    <p class="mt-2 truncate text-xs text-zinc-400" title="{{ __('Preparer') }}">
                                        {{ $preparer?->assignedMembership?->user?->name }}
                                    </p>
                                    <p class="mt-1 truncate text-xs text-zinc-500" title="{{ __('Reviewer and responsible manager') }}">
                                        {{ $reviewer?->assignedMembership?->user?->name }} · {{ $manager?->assignedMembership?->user?->name }}
                                    </p>
                                    @if ($obligation->workItem->checklist)
                                        <p class="mt-2 text-xs text-zinc-400">
                                            {{ __('Checklist :completed/:total', [
                                                'completed' => $checklistCompleted,
                                                'total' => $checklistTotal,
                                            ]) }}
                                        </p>
                                    @endif
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ __('Workflow v:version', ['version' => $obligation->workItem->workflowDefinition->version]) }}
                                    </p>
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
                                    <div class="mt-2 flex flex-wrap gap-1 lg:justify-end">
                                    @if ($obligation->workItem->checklist)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="clipboard-document-check"
                                            wire:click="openChecklist('{{ $obligation->workItem->id }}')"
                                        >
                                            {{ __('Checklist') }}
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
                                            {{ __('Update work') }}
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
                                                {{ __('Manage team') }}
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-up-circle"
                                                wire:click="openMigration('{{ $obligation->workItem->id }}')"
                                            >
                                                {{ __('Migrate version') }}
                                            </flux:button>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="exclamation-triangle"
                                                wire:click="openRisk('{{ $obligation->workItem->id }}')"
                                            >
                                                {{ __('Update risk') }}
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
                                            {{ $obligation->filingRecord ? __('Update filing') : __('Open filing') }}
                                        </flux:button>
                                    @endif
                                    @if ($obligation->paymentRecord ? Gate::allows('transition', $obligation->paymentRecord) : Gate::allows('create', \App\Models\PaymentRecord::class))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="banknotes"
                                            wire:click="openPayment('{{ $obligation->id }}')"
                                        >
                                            {{ $obligation->paymentRecord ? __('Update payment') : __('Open payment') }}
                                        </flux:button>
                                    @endif
                                    @if ($obligation->taxRecord ? Gate::allows('amend', $obligation->taxRecord) : Gate::allows('create', \App\Models\TaxRecord::class))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="calculator"
                                            wire:click="openTax('{{ $obligation->id }}')"
                                        >
                                            {{ $obligation->taxRecord ? __('Update tax') : __('Open tax') }}
                                        </flux:button>
                                    @endif
                                    </div>
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
                                {{ $search === '' ? __('No manual obligations recorded') : __('No obligations match this search') }}
                            </p>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">
                                {{ $search === ''
                                    ? __('Select an active client and record the first reviewed manual deadline.')
                                    : __('Check the client, obligation type or period and try again.') }}
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
        <aside aria-labelledby="create-obligation-heading" class="xl:sticky xl:top-8 xl:self-start">
            <div class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <div class="mb-6">
                    <span class="mb-4 grid size-10 place-items-center rounded-xl bg-amber-400 text-black">
                        <flux:icon.calendar-days class="size-5" />
                    </span>
                    <h2 id="create-obligation-heading" class="text-lg font-semibold text-zinc-100">{{ __('Record manual obligation') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        {{ __('Enter only a date reviewed by an authorised person. The source note remains part of the record.') }}
                    </p>
                </div>

                @if ($this->clients->isEmpty())
                    <flux:callout
                        variant="warning"
                        icon="exclamation-triangle"
                        :heading="__('An active client is required')"
                    >
                        {{ __('Ask a firm administrator to create or reactivate a canonical client before recording an obligation.') }}
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
                            :label="__('Obligation type')"
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

                        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
                            <flux:input
                                wire:model="statutoryDueDate"
                                type="date"
                                :label="__('Statutory due date')"
                                required
                            />

                            <flux:input
                                wire:model="internalTargetDate"
                                type="date"
                                :label="__('Internal target date')"
                                :description="__('Optional. Must not be later than the statutory due date.')"
                            />
                        </div>

                        <flux:input
                            wire:model="lastVerifiedOn"
                            type="date"
                            :label="__('Last verified on')"
                            max="{{ now('Asia/Dubai')->toDateString() }}"
                            required
                        />

                        <flux:textarea
                            wire:model="sourceReference"
                            :label="__('Source or verification note')"
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
                            <span wire:loading.remove wire:target="createObligation">{{ __('Record manual obligation') }}</span>
                            <span wire:loading wire:target="createObligation">{{ __('Recording obligation...') }}</span>
                        </flux:button>
                    </form>
                @endif
            </div>

            <p class="mt-4 px-1 text-xs leading-5 text-zinc-600">
                {{ __('Recording a deadline creates no work item. Team assignment remains a separate, explicit action.') }}
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
                <flux:heading size="lg">{{ __('Cancel or supersede obligation') }}</flux:heading>
                <flux:text class="mt-2">{{ $dispositionLabel }}</flux:text>
                <flux:text class="mt-2 text-zinc-500">{{ __('The obligation and all retained evidence remain unchanged. Supersession requires a separately issued open replacement.') }}</flux:text>
            </div>
            <flux:select wire:model.live="dispositionStatus" :label="__('Disposition')" required>
                <flux:select.option value="">{{ __('Select disposition') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                <flux:select.option value="superseded">{{ __('Superseded') }}</flux:select.option>
            </flux:select>
            @if ($dispositionStatus === 'superseded')
                <flux:select wire:model="replacementObligationId" :label="__('Replacement obligation')" required>
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
                <flux:button type="submit" variant="primary">{{ __('Record disposition') }}</flux:button>
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
                <flux:heading size="lg">{{ __('Override effective deadline') }}</flux:heading>
                <flux:text class="mt-2">{{ $deadlineOverrideLabel }}</flux:text>
                <flux:text class="mt-2 text-zinc-500">
                    {{ __('The original statutory date is preserved. This change affects operational urgency and ordering only.') }}
                </flux:text>
            </div>
            <flux:input wire:model="deadlineOverrideDate" type="date" :label="__('New effective due date')" required />
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
                    {{ __('Create one separate work item for :obligation. The three initial assignments are retained as history events.', [
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
                :description="__('Required. This reason is retained with each initial assignment event.')"
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
                <flux:heading size="lg">{{ __('Update work status') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Move :work from :status through an allowed role-controlled transition. The reason and previous state are retained.', [
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
                    :heading="__('No transition is available')"
                >
                    {{ __('The current state is terminal or the next action belongs to another assigned role. Close this window and ask the responsible assignee to continue.') }}
                </flux:callout>
            @else
                <flux:select wire:model.live="targetWorkItemStatus" :label="__('Next work status')" required>
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
                                '{0} This checklist has no required items.|{1} The required checklist item has retained evidence.|[2,*] All :count required checklist items have retained evidence.',
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
                            {{ __(':completed of :total required items have retained evidence. Close this window, open Work checklist, and complete the remaining items before submitting for review.', [
                                'completed' => $selectedRequiredChecklistCompleted,
                                'total' => $selectedRequiredChecklistTotal,
                            ]) }}
                        </flux:callout>
                    @endif
                @endif

                <flux:textarea
                    wire:model="transitionReason"
                    :label="__('Transition reason')"
                    :description="__('Required. Record the operational reason without including credentials or private documents.')"
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
                :description="__('Required. Record the operational reason without including credentials or private documents.')"
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
                    {{ __('Review the checklist version pinned to :work. Completed items retain their original evidence and cannot be reopened in this packet.', [
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
                            <p class="text-sm font-medium text-zinc-200">{{ __('Retain completion evidence') }}</p>
                            <p class="mt-1 text-sm leading-6 text-zinc-500">
                                {{ __('Select one item and record concise evidence. This completion cannot be edited or removed.') }}
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
                            :label="__('Evidence note')"
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
                        {{ __('Every item in this pinned version has retained completion evidence.') }}
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

            <div class="divide-y divide-white/8 border-y border-white/8">
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
                    :description="__('Required. Record the operational reason without including credentials or private documents.')"
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
                    :description="__('Required. Record the operational reason without including credentials or private documents.')"
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
                <flux:heading size="lg">{{ __('Update risk status') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Record the assessed risk for :work. Risk status is stored independently of work, filing, payment and tax state.', [
                        'work' => $riskWorkItemLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:error name="riskLevel" />

            <flux:select wire:model="riskLevel" :label="__('Risk level')" required>
                @foreach (\App\Enums\RiskLevel::cases() as $level)
                    <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="riskReason"
                :label="__('Reason')"
                :description="__('Required. Record the operational reason without including credentials or private documents.')"
                rows="3"
                maxlength="500"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeRiskModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveRisk">
                    <span wire:loading.remove wire:target="saveRisk">{{ __('Record risk status') }}</span>
                    <span wire:loading wire:target="saveRisk">{{ __('Recording risk status...') }}</span>
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
                    {{ __('Record retained tax figures for :work. These are entered or externally computed values. This platform does not calculate a statutory amount, and tax figures are stored separately from work, filing and payment state.', [
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
                :description="__('Required. Record the operational reason without including credentials or private documents.')"
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
                <flux:heading size="lg">{{ __('Migrate workflow version') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Move :work from pinned version :version to a later published workflow version. Transition, assignment and checklist history are preserved unchanged.', [
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
                    {{ __('This work is already pinned to the latest published workflow version. Close this window; no repin is possible or needed.') }}
                </flux:callout>
            @else
                <flux:select wire:model="migrationTargetDefinitionId" :label="__('Target workflow version')" required>
                    <flux:select.option value="">{{ __('Select a later published version') }}</flux:select.option>
                    @foreach ($this->availableWorkflowVersions as $definition)
                        <flux:select.option :value="$definition->id">
                            {{ __('Version :version', ['version' => $definition->version]) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    wire:model="migrationReason"
                    :label="__('Migration reason')"
                    :description="__('Required. Record the operational reason without including credentials or private documents.')"
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
                        <span wire:loading.remove wire:target="migrateWorkflowVersion">{{ __('Record migration') }}</span>
                        <span wire:loading wire:target="migrateWorkflowVersion">{{ __('Recording migration...') }}</span>
                    </flux:button>
                @endif
            </div>
        </form>
    </flux:modal>
</div>
