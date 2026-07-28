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
                    </article>
                @empty
                    <div class="border-y border-white/8 px-6 py-14 text-center"><p class="text-sm font-medium text-zinc-200">{{ __('No party records') }}</p><p class="mt-2 text-sm text-zinc-500">{{ __('Record a synthetic party manually to begin.') }}</p></div>
                @endforelse
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
            @endcan

            @if ($decisionProposalId !== '')
                <form wire:submit="decideCorrection" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Decide correction') }}</h2>
                    <flux:select wire:model="decision" :label="__('Decision')" required><flux:select.option value="">{{ __('Select decision') }}</flux:select.option><flux:select.option value="approved">{{ __('Approve') }}</flux:select.option><flux:select.option value="rejected">{{ __('Reject') }}</flux:select.option></flux:select>
                    <flux:textarea wire:model="decisionReason" :label="__('Decision reason')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Record decision') }}</flux:button>
                </form>
            @endif
        </aside>
    </div>
</div>
