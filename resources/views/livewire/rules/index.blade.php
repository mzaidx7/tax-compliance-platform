<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="mb-3 text-sm font-medium text-amber-300">{{ __('Governed configuration') }}</p>
                <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Obligation rule governance') }}</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">
                    {{ __('Prepare source-linked rule versions, separate preparation from verification, and publish only code-backed calculators.') }}
                </p>
            </div>
            <flux:badge color="amber" icon="shield-check">{{ __('No automated obligation generation') }}</flux:badge>
        </div>
    </header>

    <div class="mt-9 grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section aria-labelledby="rule-register-heading">
            <h2 id="rule-register-heading" class="text-lg font-semibold text-zinc-100">{{ __('Rule register') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Published content is immutable. A correction starts a new version.') }}</p>

            <div class="mt-5 space-y-5">
                @forelse ($this->templates as $template)
                    <article class="rounded-2xl bg-zinc-900/50 p-5 ring-1 ring-white/8">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-zinc-100">{{ $template->name }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $template->key }} / {{ $template->jurisdiction }} / {{ $template->authority }}</p>
                            </div>
                            <flux:badge color="zinc">{{ $template->obligation_type }}</flux:badge>
                        </div>

                        <div class="mt-5 divide-y divide-white/8 border-y border-white/8">
                            @forelse ($template->versions as $version)
                                <div class="grid gap-3 py-4 lg:grid-cols-[5rem_8rem_minmax(0,1fr)_10rem] lg:items-center">
                                    <span class="text-sm font-medium text-zinc-200">v{{ $version->version }}</span>
                                    <flux:badge :color="$version->status->badgeColor()">{{ $version->status->label() }}</flux:badge>
                                    <div>
                                        <a href="{{ $version->official_source_url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-amber-300 hover:text-amber-200">
                                            {{ $version->official_source_title }}
                                        </a>
                                        <p class="mt-1 text-xs text-zinc-500">
                                            {{ $version->calculator_key }} / {{ $version->effective_from->format('d M Y') }}
                                            @if ($version->effective_to)
                                                to {{ $version->effective_to->format('d M Y') }}
                                            @endif
                                        </p>
                                    </div>
                                    <button type="button" wire:click="$set('lifecycleVersionId', '{{ $version->id }}')" class="text-left text-xs font-medium text-zinc-400 hover:text-white lg:text-right">
                                        {{ $lifecycleVersionId === $version->id ? __('Selected') : __('Select lifecycle') }}
                                    </button>
                                </div>
                            @empty
                                <p class="py-5 text-sm text-zinc-500">{{ __('No version drafted.') }}</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="border-y border-white/8 px-6 py-14 text-center">
                        <p class="text-sm font-medium text-zinc-200">{{ __('No governed rules recorded') }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">{{ __('Create a stable template, then draft its first source-linked version.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 class="text-lg font-semibold text-zinc-100">{{ __('Lifecycle action') }}</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Select a version in the register. Every action requires a retained reason.') }}</p>
                <div class="mt-5 space-y-4">
                    <flux:input wire:model="sourceLastVerifiedOn" type="date" :label="__('Source verified on')" />
                    <flux:textarea wire:model="lifecycleReason" :label="__('Reason')" maxlength="500" required />
                    <div class="grid grid-cols-2 gap-2">
                        <flux:button wire:click="submitReview" variant="ghost">{{ __('Submit review') }}</flux:button>
                        <flux:button wire:click="approve" variant="ghost">{{ __('Approve') }}</flux:button>
                        <flux:button wire:click="publish" variant="filled">{{ __('Publish') }}</flux:button>
                        <flux:button wire:click="retire" variant="danger">{{ __('Retire') }}</flux:button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 class="text-lg font-semibold text-zinc-100">{{ __('Create template') }}</h2>
                <form wire:submit="createTemplate" class="mt-5 space-y-4">
                    <flux:input wire:model="templateKey" :label="__('Stable key')" placeholder="manual_vat_filing" required />
                    <flux:input wire:model="templateName" :label="__('Name')" required />
                    <flux:input wire:model="obligationType" :label="__('Obligation type')" required />
                    <flux:input wire:model="jurisdiction" :label="__('Jurisdiction')" required />
                    <flux:input wire:model="authority" :label="__('Authority')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Create template') }}</flux:button>
                </form>
            </section>

            <section class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 class="text-lg font-semibold text-zinc-100">{{ __('Draft version') }}</h2>
                <form wire:submit="draftVersion" class="mt-5 space-y-4">
                    <flux:select wire:model="ruleTemplateId" :label="__('Template')" required>
                        <flux:select.option value="">{{ __('Select template') }}</flux:select.option>
                        @foreach ($this->templates as $template)
                            <flux:select.option :value="$template->id">{{ $template->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="effectiveFrom" type="date" :label="__('Effective from')" required />
                    <flux:input wire:model="effectiveTo" type="date" :label="__('Effective to')" />
                    <flux:textarea wire:model="applicabilityCriteria" :label="__('Applicability criteria')" maxlength="4000" required />
                    <flux:select wire:model="calculatorKey" :label="__('Registered calculator')" required>
                        @foreach ($this->calculators as $calculator)
                            <flux:select.option :value="$calculator->key()">{{ $calculator->key() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:textarea wire:model="parametersJson" :label="__('Validated parameters JSON')" required />
                    <flux:input wire:model="officialSourceTitle" :label="__('Official source title')" required />
                    <flux:input wire:model="officialSourceUrl" type="url" :label="__('Official source HTTPS URL')" required />
                    <flux:input wire:model="sourcePublishedOn" type="date" :label="__('Source published on')" />
                    <flux:textarea wire:model="changeSummary" :label="__('Change summary')" maxlength="500" required />
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Create immutable draft snapshot') }}</flux:button>
                </form>
            </section>
        </aside>
    </div>
</div>
