<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <p class="mb-3 text-sm font-medium text-amber-300">{{ __('E-invoicing readiness') }}</p>
        <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Party master and provenance') }}</h1>
        <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">{{ __('Record synthetic party identities and field sources manually. Corrections remain proposals until an authorised person approves or rejects them. No file is imported and no identity is merged automatically.') }}</p>
    </header>

    <div class="mt-9 grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section>
            <h2 class="text-lg font-semibold text-zinc-100">{{ __('Party register') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Current values are derived from append-only field versions; earlier values remain retained.') }}</p>
            <div class="mt-5 space-y-5">
                @forelse ($this->parties as $party)
                    <article class="rounded-2xl bg-zinc-900/50 p-5 ring-1 ring-white/8">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-zinc-100">{{ $party->reference }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $party->client->internal_code }} / {{ $party->client->legal_name }}</p>
                            </div>
                            <div class="flex gap-2">
                                @if ($party->is_customer)<flux:badge color="amber">{{ __('Customer') }}</flux:badge>@endif
                                @if ($party->is_supplier)<flux:badge color="zinc">{{ __('Supplier') }}</flux:badge>@endif
                            </div>
                        </div>
                        <div class="mt-5 divide-y divide-white/8 border-y border-white/8">
                            @foreach ($party->fieldVersions->groupBy(fn ($field) => $field->field_key->value) as $versions)
                                @php($current = $versions->last())
                                <div class="grid gap-3 py-4 sm:grid-cols-[8rem_minmax(0,1fr)_8rem_6rem] sm:items-center">
                                    <span class="text-xs font-medium text-zinc-400">{{ $current->field_key->label() }}</span>
                                    <div>
                                        <p class="text-sm text-zinc-200">{{ $current->value }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ __('Source: :source / :count retained version(s)', ['source' => $current->source_kind, 'count' => $versions->count()]) }}</p>
                                    </div>
                                    <flux:badge color="zinc">{{ $current->verification_state->label() }}</flux:badge>
                                    @can('update', $party)
                                        <flux:button size="sm" variant="ghost" wire:click="$set('currentFieldVersionId', '{{ $current->id }}')">{{ __('Correct') }}</flux:button>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                        @if ($party->correctionProposals->isNotEmpty())
                            <div class="mt-5 space-y-3">
                                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Correction decisions') }}</p>
                                @foreach ($party->correctionProposals as $proposal)
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-950/60 px-4 py-3 ring-1 ring-white/8">
                                        <div>
                                            <p class="text-sm text-zinc-300">{{ $proposal->currentFieldVersion->field_key->label() }}</p>
                                            <p class="mt-1 text-xs text-zinc-500">{{ $proposal->decision ? ucfirst($proposal->decision->decision) : __('Awaiting decision') }}</p>
                                        </div>
                                        @can('approveCorrection', $party)
                                            @if (! $proposal->decision)
                                                <flux:button size="sm" variant="ghost" wire:click="$set('decisionProposalId', '{{ $proposal->id }}')">{{ __('Decide') }}</flux:button>
                                            @endif
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if ($party->issues->isNotEmpty())
                            <div class="mt-5 space-y-3">
                                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Explainable issues') }}</p>
                                @foreach ($party->issues as $issue)
                                    <div class="rounded-xl bg-zinc-950/60 px-4 py-3 ring-1 ring-white/8">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-medium text-zinc-200">{{ $issue->ruleVersion->definition->name }}</p>
                                                <p class="mt-1 text-xs leading-5 text-zinc-500">{{ $issue->explanation_snapshot }}</p>
                                                <p class="mt-1 text-xs leading-5 text-zinc-500">{{ __('Remediation: :guidance', ['guidance' => $issue->remediation_snapshot]) }}</p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <flux:badge :color="$issue->behavior_snapshot->value === 'blocking' ? 'red' : 'amber'">{{ $issue->severity_snapshot->label() }} / {{ $issue->behavior_snapshot->label() }}</flux:badge>
                                                <flux:badge :color="$issue->resolution ? 'green' : 'zinc'">{{ $issue->resolution ? str($issue->resolution->outcome)->replace('_', ' ')->title() : __('Open') }}</flux:badge>
                                            </div>
                                        </div>
                                        @can('approveCorrection', $party)
                                            @if (! $issue->resolution)
                                                <flux:button class="mt-3" size="sm" variant="ghost" wire:click="$set('resolutionIssueId', '{{ $issue->id }}')">{{ __('Resolve issue') }}</flux:button>
                                            @endif
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="border-y border-white/8 px-6 py-14 text-center"><p class="text-sm font-medium text-zinc-200">{{ __('No party records') }}</p><p class="mt-2 text-sm text-zinc-500">{{ __('Record a synthetic party manually to begin.') }}</p></div>
                @endforelse
            </div>

            <div class="mt-10 border-t border-white/8 pt-8">
                <h2 class="text-lg font-semibold text-zinc-100">{{ __('Duplicate candidate review') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Each candidate shows manually selected deterministic signals. No probability is calculated and no party is merged.') }}</p>
                <div class="mt-5 space-y-4">
                    @forelse ($this->duplicateCandidates as $candidate)
                        <article class="rounded-2xl bg-zinc-900/50 p-5 ring-1 ring-white/8">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-zinc-100">{{ $candidate->firstParty->reference }} / {{ $candidate->secondParty->reference }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $candidate->firstParty->client->legal_name }}</p>
                                </div>
                                <flux:badge :color="$candidate->decision ? ($candidate->decision->outcome->value === 'confirmed' ? 'amber' : 'green') : 'zinc'">
                                    {{ $candidate->decision ? $candidate->decision->outcome->label() : __('Awaiting decision') }}
                                </flux:badge>
                            </div>
                            <div class="mt-4 space-y-3">
                                @foreach ($candidate->signals as $signal)
                                    <div class="rounded-xl bg-zinc-950/60 px-4 py-3 ring-1 ring-white/8">
                                        <p class="text-sm font-medium text-zinc-200">{{ $signal->signal_type->label() }}</p>
                                        <p class="mt-1 text-xs leading-5 text-zinc-500">{{ $signal->contribution_explanation }}</p>
                                        <p class="mt-1 text-xs text-zinc-600">{{ __('Normalizer: :version', ['version' => $signal->normalizer_version]) }}</p>
                                    </div>
                                @endforeach
                            </div>
                            @can('approveCorrection', $candidate->firstParty)
                                @if (! $candidate->decision)
                                    <flux:button class="mt-4" size="sm" variant="ghost" wire:click="$set('duplicateCandidateId', '{{ $candidate->id }}')">{{ __('Decide candidate') }}</flux:button>
                                @endif
                            @endcan
                        </article>
                    @empty
                        <div class="border-y border-white/8 px-6 py-10 text-center"><p class="text-sm text-zinc-500">{{ __('No duplicate candidates have been recorded.') }}</p></div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            @can('create', \App\Models\PartyRecord::class)
                <form wire:submit="createParty" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Record party identity') }}</h2>
                    <flux:select wire:model="clientId" :label="__('Client')" required><flux:select.option value="">{{ __('Select client') }}</flux:select.option>@foreach ($this->clients as $client)<flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>@endforeach</flux:select>
                    <flux:input wire:model="reference" :label="__('Synthetic source reference')" required />
                    <flux:checkbox wire:model="isCustomer" :label="__('Customer role')" />
                    <flux:checkbox wire:model="isSupplier" :label="__('Supplier role')" />
                    <flux:checkbox wire:model="isActive" :label="__('Supplied as active')" />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Record immutable identity') }}</flux:button>
                </form>

                <form wire:submit="addField" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Record initial field') }}</h2>
                    <flux:select wire:model="partyId" :label="__('Party')" required><flux:select.option value="">{{ __('Select party') }}</flux:select.option>@foreach ($this->parties as $party)<flux:select.option :value="$party->id">{{ $party->reference }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="fieldKey" :label="__('Field')" required>@foreach ($this->fieldKeys() as $key)<flux:select.option :value="$key->value">{{ $key->label() }}</flux:select.option>@endforeach</flux:select>
                    <flux:input wire:model="fieldValue" :label="__('Supplied value')" required />
                    <flux:select wire:model="verificationState" :label="__('Verification state')">@foreach ($this->verificationStates() as $state)<flux:select.option :value="$state->value">{{ $state->label() }}</flux:select.option>@endforeach</flux:select>
                    <flux:textarea wire:model="sourceReference" :label="__('Manual source reference')" required />
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Record provenance') }}</flux:button>
                </form>

                <form wire:submit="proposeCorrection" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Propose correction') }}</h2>
                    <flux:select wire:model="currentFieldVersionId" :label="__('Current field')" required><flux:select.option value="">{{ __('Select current field') }}</flux:select.option>@foreach ($this->parties as $party)@foreach ($party->fieldVersions->groupBy(fn ($field) => $field->field_key->value) as $versions)@php($current = $versions->last())<flux:select.option :value="$current->id">{{ $party->reference }} / {{ $current->field_key->label() }}</flux:select.option>@endforeach @endforeach</flux:select>
                    <flux:input wire:model="proposedValue" :label="__('Proposed value')" required />
                    <flux:textarea wire:model="evidenceNote" :label="__('Evidence note')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Record proposal') }}</flux:button>
                </form>

                <form wire:submit="recordIssue" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Record party issue') }}</h2>
                    <flux:select wire:model="issuePartyId" :label="__('Party')" required><flux:select.option value="">{{ __('Select party') }}</flux:select.option>@foreach ($this->parties as $party)<flux:select.option :value="$party->id">{{ $party->reference }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="issueFieldVersionId" :label="__('Current field, if applicable')"><flux:select.option value="">{{ __('Party-level issue') }}</flux:select.option>@foreach ($this->parties as $party)@foreach ($party->fieldVersions->groupBy(fn ($field) => $field->field_key->value) as $versions)@php($current = $versions->last())<flux:select.option :value="$current->id">{{ $party->reference }} / {{ $current->field_key->label() }}</flux:select.option>@endforeach @endforeach</flux:select>
                    <flux:select wire:model="issueRuleVersionId" :label="__('Published party rule')" required><flux:select.option value="">{{ __('Select rule') }}</flux:select.option>@foreach ($this->publishedPartyRules as $rule)<flux:select.option :value="$rule->id">{{ $rule->definition->name }} / v{{ $rule->version }}</flux:select.option>@endforeach</flux:select>
                    <flux:textarea wire:model="issueEvidenceNote" :label="__('Manual issue evidence')" required />
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Record explainable issue') }}</flux:button>
                </form>

                <form wire:submit="recordDuplicateSignal" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Record duplicate signal') }}</h2>
                    <p class="text-sm leading-6 text-zinc-500">{{ __('Select two parties for the same client and retain the already-normalized comparison evidence. This form performs no automatic matching.') }}</p>
                    <flux:select wire:model="duplicateFirstPartyId" :label="__('First party')" required><flux:select.option value="">{{ __('Select first party') }}</flux:select.option>@foreach ($this->parties as $party)<flux:select.option :value="$party->id">{{ $party->reference }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="duplicateSecondPartyId" :label="__('Second party')" required><flux:select.option value="">{{ __('Select second party') }}</flux:select.option>@foreach ($this->parties as $party)<flux:select.option :value="$party->id">{{ $party->reference }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="duplicateSignalType" :label="__('Signal')" required>@foreach ($this->duplicateSignalTypes() as $type)<flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>@endforeach</flux:select>
                    <flux:input wire:model="duplicateFirstValue" :label="__('First normalized value')" required />
                    <flux:input wire:model="duplicateSecondValue" :label="__('Second normalized value')" required />
                    <flux:input wire:model="duplicateNormalizerVersion" :label="__('Normalizer version')" required />
                    <flux:textarea wire:model="duplicateExplanation" :label="__('Signal contribution explanation')" required />
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Retain duplicate signal') }}</flux:button>
                </form>
            @endcan

            @if ($decisionProposalId !== '')
                <form wire:submit="decideCorrection" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Decide correction') }}</h2>
                    <flux:select wire:model="decision" :label="__('Decision')" required><flux:select.option value="">{{ __('Select decision') }}</flux:select.option><flux:select.option value="approved">{{ __('Approve') }}</flux:select.option><flux:select.option value="rejected">{{ __('Reject') }}</flux:select.option></flux:select>
                    <flux:textarea wire:model="decisionReason" :label="__('Decision reason')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Record decision') }}</flux:button>
                </form>
            @endif

            @if ($resolutionIssueId !== '')
                <form wire:submit="resolveIssue" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Resolve party issue') }}</h2>
                    <flux:select wire:model="resolutionOutcome" :label="__('Outcome')" required><flux:select.option value="">{{ __('Select outcome') }}</flux:select.option><flux:select.option value="resolved">{{ __('Resolved') }}</flux:select.option><flux:select.option value="not_applicable">{{ __('Not applicable') }}</flux:select.option></flux:select>
                    <flux:textarea wire:model="resolutionReason" :label="__('Resolution reason')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Record issue decision') }}</flux:button>
                </form>
            @endif

            @if ($duplicateCandidateId !== '')
                <form wire:submit="decideDuplicate" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Decide duplicate candidate') }}</h2>
                    <flux:select wire:model="duplicateOutcome" :label="__('Outcome')" required><flux:select.option value="">{{ __('Select outcome') }}</flux:select.option><flux:select.option value="confirmed">{{ __('Confirm duplicate') }}</flux:select.option><flux:select.option value="dismissed">{{ __('Dismiss') }}</flux:select.option></flux:select>
                    <flux:textarea wire:model="duplicateDecisionReason" :label="__('Decision reason')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Record candidate decision') }}</flux:button>
                </form>
            @endif
        </aside>
    </div>
</div>
