<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <p class="mb-3 text-sm font-medium text-amber-300">{{ __('E-invoicing readiness') }}</p>
        <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Data-quality rule governance') }}</h1>
        <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">{{ __('Govern party-master and invoice-transaction checks separately. This register does not import data, calculate readiness scores, correct records or transmit e-invoices.') }}</p>
    </header>

    <div class="mt-9 grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section>
            <h2 class="text-lg font-semibold text-zinc-100">{{ __('Readiness rule register') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Published versions are immutable and every lifecycle action is retained.') }}</p>
            <div class="mt-5 space-y-5">
                @forelse ($this->definitions as $definition)
                    <article class="rounded-2xl bg-zinc-900/50 p-5 ring-1 ring-white/8">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-zinc-100">{{ $definition->name }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $definition->key }} / {{ $definition->field_or_scenario }}</p>
                            </div>
                            <flux:badge color="zinc">{{ $definition->data_domain->label() }}</flux:badge>
                        </div>
                        <div class="mt-5 divide-y divide-white/8 border-y border-white/8">
                            @foreach ($definition->versions as $version)
                                <div class="grid gap-3 py-4 lg:grid-cols-[4rem_7rem_8rem_8rem_minmax(0,1fr)_6rem] lg:items-center">
                                    <span class="text-sm text-zinc-200">v{{ $version->version }}</span>
                                    <flux:badge :color="$version->status->badgeColor()">{{ $version->status->label() }}</flux:badge>
                                    <span class="text-xs text-zinc-400">{{ $version->severity->label() }}</span>
                                    <span class="text-xs text-zinc-400">{{ $version->behavior->label() }}</span>
                                    <div>
                                        <p class="text-sm text-zinc-300">{{ $version->explanation }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ $version->formula_version_effect }}</p>
                                    </div>
                                    <flux:button size="sm" variant="ghost" wire:click="$set('lifecycleVersionId', '{{ $version->id }}')">{{ __('Select') }}</flux:button>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="border-y border-white/8 px-6 py-14 text-center">
                        <p class="text-sm font-medium text-zinc-200">{{ __('No readiness rules recorded') }}</p>
                        <p class="mt-2 text-sm text-zinc-500">{{ __('Create a stable identity, then draft its first governed version.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-6">
            <form wire:submit="createDefinition" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 class="text-lg font-semibold text-zinc-100">{{ __('Create rule identity') }}</h2>
                <flux:input wire:model="definitionKey" :label="__('Stable key')" placeholder="party_trn_missing" required />
                <flux:input wire:model="definitionName" :label="__('Name')" required />
                <flux:select wire:model="dataDomain" :label="__('Data domain')" required>
                    @foreach ($this->domains() as $domain)<flux:select.option :value="$domain->value">{{ $domain->label() }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="fieldOrScenario" :label="__('Field or scenario')" required />
                <flux:button type="submit" variant="filled" class="w-full">{{ __('Create immutable identity') }}</flux:button>
            </form>

            <form wire:submit="draftVersion" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 class="text-lg font-semibold text-zinc-100">{{ __('Draft version') }}</h2>
                <flux:select wire:model="definitionId" :label="__('Rule identity')" required>
                    <flux:select.option value="">{{ __('Select identity') }}</flux:select.option>
                    @foreach ($this->definitions as $definition)<flux:select.option :value="$definition->id">{{ $definition->data_domain->label() }} / {{ $definition->name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:textarea wire:model="applicability" :label="__('Applicability')" required />
                <flux:select wire:model="severity" :label="__('Severity')">@foreach ($this->severities() as $option)<flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="behavior" :label="__('Behavior')">@foreach ($this->behaviors() as $option)<flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:textarea wire:model="explanation" :label="__('Explanation')" required />
                <flux:textarea wire:model="remediation" :label="__('Remediation guidance')" required />
                <flux:select wire:model="sourceKind" :label="__('Source type')"><flux:select.option value="internal">{{ __('Internal') }}</flux:select.option><flux:select.option value="official">{{ __('Official') }}</flux:select.option></flux:select>
                <flux:input wire:model="sourceTitle" :label="__('Source title')" required />
                <flux:input wire:model="sourceUrl" type="url" :label="__('Official source URL')" />
                <flux:textarea wire:model="formulaEffect" :label="__('Formula-version effect')" required />
                <flux:textarea wire:model="changeSummary" :label="__('Change summary')" required />
                <flux:button type="submit" variant="primary" class="w-full">{{ __('Create immutable draft') }}</flux:button>
            </form>

            <form wire:submit="transitionRule" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 class="text-lg font-semibold text-zinc-100">{{ __('Lifecycle action') }}</h2>
                <flux:select wire:model="targetStatus" :label="__('Target status')" required>
                    <flux:select.option value="">{{ __('Select target') }}</flux:select.option>
                    <flux:select.option value="under_review">{{ __('Under review') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                    <flux:select.option value="published">{{ __('Published') }}</flux:select.option>
                    <flux:select.option value="retired">{{ __('Retired') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="sourceLastVerifiedOn" type="date" :label="__('Source verified on')" />
                <flux:textarea wire:model="lifecycleReason" :label="__('Reason')" maxlength="500" required />
                <flux:button type="submit" variant="filled" class="w-full">{{ __('Record lifecycle action') }}</flux:button>
            </form>
        </aside>
    </div>
</div>
