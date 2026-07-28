<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="mb-3 text-sm font-medium text-amber-300">
                    {{ app(\App\Tenancy\FirmContext::class)->firm()->name }}
                </p>
                <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                    {{ __('Work tracker') }}
                </h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">
                    {{ __('Track each compliance job, its assigned preparer, reviewer and manager, its current stage, and any follow-up work.') }}
                </p>
            </div>

            <flux:button :href="route('obligations.index')" variant="filled" icon="calendar-days" wire:navigate>
                {{ __('Open obligations') }}
            </flux:button>
        </div>
    </header>

    <section class="mt-8" aria-labelledby="work-register-filters">
        <h2 id="work-register-filters" class="sr-only">{{ __('Filter work') }}</h2>
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_16rem_auto] sm:items-end">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :label="__('Search work')"
                :placeholder="__('Client, obligation or period')"
                icon="magnifying-glass"
            />

            <flux:select wire:model.live="status" :label="__('Work status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($this->statuses as $workStatus)
                    <flux:select.option :value="$workStatus->value">{{ $workStatus->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button variant="ghost" wire:click="clearFilters">
                {{ __('Clear filters') }}
            </flux:button>
        </div>
        <div class="mt-4 grid gap-3 border-t border-white/8 pt-4 lg:grid-cols-[minmax(12rem,1fr)_auto_minmax(12rem,1fr)_auto] lg:items-end">
            <flux:select wire:model="selectedSavedFilterId" :label="__('Saved filter')">
                <flux:select.option value="">{{ __('Select your saved filter') }}</flux:select.option>
                @foreach ($this->savedFilters as $savedFilter)
                    <flux:select.option :value="$savedFilter->id">{{ $savedFilter->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex gap-2">
                <flux:button variant="ghost" wire:click="applySavedFilter" :disabled="$selectedSavedFilterId === ''">{{ __('Apply') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteSavedFilter" :disabled="$selectedSavedFilterId === ''" wire:confirm="{{ __('Delete this saved filter?') }}">{{ __('Delete') }}</flux:button>
            </div>
            <flux:input wire:model="savedFilterName" :label="__('Save current filters as')" :placeholder="__('My review queue')" maxlength="80" />
            <flux:button variant="filled" wire:click="saveFilter" :disabled="trim($savedFilterName) === ''">{{ __('Save filter') }}</flux:button>
        </div>
    </section>

    <section class="mt-7" aria-labelledby="work-register-results">
        <div class="mb-3 flex items-center justify-between gap-4">
            <h2 id="work-register-results" class="text-sm font-semibold text-zinc-200">
                {{ trans_choice('{0} No work groups|{1} :count work group|[2,*] :count work groups', $this->workGroups->total(), ['count' => $this->workGroups->total()]) }}
            </h2>
            <p class="text-xs text-zinc-500">{{ __('Ordered by statutory due date') }}</p>
        </div>

        <div class="space-y-4">
            @forelse ($this->workGroups as $primary)
                @php
                    $timeline = collect([$primary])->concat($primary->followUps);
                @endphp

                <article class="overflow-hidden rounded-2xl bg-zinc-900/70 ring-1 ring-white/8">
                    <header class="flex flex-col gap-3 border-b border-white/8 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-white">{{ $primary->obligation->client->internal_code }}</span>
                                <span class="text-zinc-600" aria-hidden="true">/</span>
                                <span class="truncate text-sm text-zinc-300">{{ $primary->obligation->obligation_type }}</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $primary->obligation->period_label ?: __('No period label') }}
                                ·
                                {{ __('Effective due :date', ['date' => $primary->obligation->effectiveDueDate()->format('d M Y')]) }}
                                @if (! $primary->obligation->effectiveDueDate()->isSameDay($primary->obligation->statutory_due_date))
                                    · {{ __('Statutory :date', ['date' => $primary->obligation->statutory_due_date->format('d M Y')]) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:badge color="zinc">
                                {{ trans_choice('{0} Original only|{1} 1 corrective follow-up|[2,*] :count corrective follow-ups', $primary->followUps->count(), ['count' => $primary->followUps->count()]) }}
                            </flux:badge>
                        </div>
                    </header>

                    <ol class="divide-y divide-white/8" aria-label="{{ __('Work history for :obligation', ['obligation' => $primary->obligation->obligation_type]) }}">
                        @foreach ($timeline as $position => $workItem)
                            @php
                                $manager = $workItem->currentAssignment(\App\Enums\AssignmentRole::ResponsibleManager);
                                $preparer = $workItem->currentAssignment(\App\Enums\AssignmentRole::Preparer);
                                $reviewer = $workItem->currentAssignment(\App\Enums\AssignmentRole::Reviewer);
                            @endphp

                            <li class="grid gap-4 px-5 py-4 lg:grid-cols-[9rem_minmax(0,1fr)_auto] lg:items-center">
                                <div>
                                    <p class="text-sm font-medium {{ $position === 0 ? 'text-zinc-200' : 'text-amber-300' }}">
                                        {{ $position === 0 ? __('Primary work') : __('Follow-up :number', ['number' => $position]) }}
                                    </p>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ $workItem->created_at?->format('d M Y, H:i') }}
                                    </p>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:badge :color="$workItem->status->badgeColor()">{{ $workItem->status->label() }}</flux:badge>
                                        <flux:badge :color="$workItem->risk_status->badgeColor()">{{ __('Risk: :risk', ['risk' => $workItem->risk_status->label()]) }}</flux:badge>
                                        <span class="text-xs text-zinc-500">{{ __('Workflow v:version', ['version' => $workItem->workflowDefinition->version]) }}</span>
                                    </div>
                                    <p class="mt-2 truncate text-sm text-zinc-400">
                                        {{ __('Preparer: :preparer · Reviewer: :reviewer · Manager: :manager', [
                                            'preparer' => $preparer?->assignedMembership?->user?->name ?? __('Unassigned'),
                                            'reviewer' => $reviewer?->assignedMembership?->user?->name ?? __('Unassigned'),
                                            'manager' => $manager?->assignedMembership?->user?->name ?? __('Unassigned'),
                                        ]) }}
                                    </p>

                                    @if ($workItem->documentEvidence->isNotEmpty())
                                        <ul class="mt-3 space-y-2" aria-label="{{ __('Retained document evidence') }}">
                                            @foreach ($workItem->documentEvidence->sortBy('uploaded_at') as $evidence)
                                                @php($scan = $evidence->latestScan())
                                                <li class="flex flex-wrap items-center gap-2 text-xs">
                                                    <span class="max-w-56 truncate text-zinc-300">{{ $evidence->original_name }}</span>
                                                    <flux:badge :color="$scan?->verdict === \App\Enums\MalwareScanVerdict::Clean ? 'green' : ($scan?->verdict === \App\Enums\MalwareScanVerdict::Infected ? 'red' : 'amber')">
                                                        {{ $scan?->verdict->label() ?? __('Quarantined') }}
                                                    </flux:badge>
                                                    <span class="text-zinc-500">{{ $evidence->purpose->label() }}</span>
                                                    @if ($scan?->verdict === \App\Enums\MalwareScanVerdict::Clean)
                                                        <a
                                                            href="{{ route('documents.download', $evidence) }}"
                                                            class="font-medium text-amber-300 underline decoration-amber-300/40 underline-offset-4 hover:text-amber-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                                                        >
                                                            {{ __('Download') }}
                                                        </a>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <div class="flex flex-col items-start gap-2 lg:items-end">
                                    @if ($position === 0 && $primary->followUps->isNotEmpty())
                                        <p class="text-xs font-medium text-green-300">{{ __('Original preserved') }}</p>
                                    @elseif ($workItem->status->value === 'completed')
                                        <p class="text-xs font-medium text-green-300">{{ __('Closed evidence') }}</p>
                                    @else
                                        <p class="text-xs text-zinc-500">{{ __('Active work record') }}</p>
                                    @endif

                                    @if (! in_array($workItem->status, [\App\Enums\WorkItemStatus::Completed, \App\Enums\WorkItemStatus::Cancelled], true) && Gate::allows('evidence', $workItem))
                                        <flux:button size="sm" variant="ghost" icon="paper-clip" wire:click="openEvidence('{{ $workItem->id }}')">
                                            {{ __('Add evidence') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </article>
            @empty
                <div class="rounded-2xl bg-zinc-900/70 px-6 py-14 text-center ring-1 ring-white/8">
                    <p class="text-sm font-medium text-zinc-200">{{ __('No work matches these filters') }}</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">
                        {{ __('Clear the filters or open obligations to create and assign work.') }}
                    </p>
                </div>
            @endforelse
        </div>

        @if ($this->workGroups->hasPages())
            <div class="mt-6">{{ $this->workGroups->links() }}</div>
        @endif
    </section>

    <flux:modal wire:model.self="showEvidenceModal" class="md:w-[34rem]">
        <form wire:submit="saveEvidence" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add document evidence') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Attach evidence to :work. Files stay private and quarantined unless the configured malware scanner returns a clean result.', ['work' => $evidenceWorkItemLabel]) }}
                </flux:text>
            </div>

            <flux:select wire:model="evidencePurpose" :label="__('Document purpose')" required>
                @foreach ($this->evidencePurposes as $purpose)
                    <flux:select.option :value="$purpose->value">{{ $purpose->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                type="file"
                wire:model="documentUpload"
                :label="__('Document')"
                accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                required
            />
            <flux:error name="documentUpload" />
            <p class="text-xs leading-5 text-zinc-500">
                {{ __('PDF, PNG or JPEG. Maximum 10 MB. The detected file type must match its extension.') }}
            </p>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeEvidence">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="filled" wire:loading.attr="disabled" wire:target="documentUpload,saveEvidence">
                    <span wire:loading.remove wire:target="saveEvidence">{{ __('Store and scan') }}</span>
                    <span wire:loading wire:target="saveEvidence">{{ __('Scanning...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
