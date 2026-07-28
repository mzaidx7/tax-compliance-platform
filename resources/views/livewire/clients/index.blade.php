{{--
THESIS: Client identity begins as a controlled firm ledger, not a collection of profile cards.
OWN-WORLD: Matte ink surfaces, silver hierarchy, and restrained gold creation cues.
STORY: The administrator scans the current register, searches identity records, and adds one canonical client.
FIRST VIEWPORT: The searchable register leads while the creation panel remains visible beside it on wide screens.
FORM: Continuous identity ledger with a compact creation station.
--}}
<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="mb-3 text-sm font-medium text-amber-300">{{ $this->currentFirmName }}</p>
                <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                    {{ __('Client register') }}
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-zinc-400">
                    {{ __('Create the canonical client identity before adding contacts, tax registrations, periods or compliance work.') }}
                </p>
            </div>
            <flux:badge color="amber" icon="shield-check">{{ __('Firm administrator controlled') }}</flux:badge>
        </div>
    </header>

    <div class="mt-9 grid gap-10 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="client-register-heading">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="client-register-heading" class="text-lg font-semibold text-zinc-100">{{ __('Canonical identities') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Codes are unique inside the active firm workspace.') }}</p>
                </div>
                <div class="w-full sm:max-w-xs">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        :label="__('Search clients')"
                        placeholder="Code, legal or trade name"
                        icon="magnifying-glass"
                    />
                </div>
            </div>

            <div class="overflow-hidden border-y border-white/8">
                <div class="hidden grid-cols-[8rem_minmax(0,1.4fr)_minmax(0,1fr)_7rem] gap-4 border-b border-white/8 px-4 py-3 text-xs font-medium text-zinc-500 sm:grid">
                    <span>{{ __('Code') }}</span>
                    <span>{{ __('Legal identity') }}</span>
                    <span>{{ __('Entity type') }}</span>
                    <span class="text-right">{{ __('Status') }}</span>
                </div>

                <div class="divide-y divide-white/8">
                    @forelse ($this->clients as $client)
                        <article
                            wire:key="client-{{ $client->id }}"
                            class="grid gap-4 px-4 py-5 transition-colors duration-150 hover:bg-white/[0.025] sm:grid-cols-[8rem_minmax(0,1.4fr)_minmax(0,1fr)_7rem] sm:items-center"
                        >
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 sm:hidden">{{ __('Code') }}</span>
                                <span class="font-medium text-zinc-200">{{ $client->internal_code }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="mb-1 block text-xs text-zinc-500 sm:hidden">{{ __('Legal identity') }}</span>
                                <p class="truncate text-sm font-medium text-zinc-100">{{ $client->legal_name }}</p>
                                @if ($client->trade_name)
                                    <p class="mt-1 truncate text-sm text-zinc-500">{{ $client->trade_name }}</p>
                                @endif
                            </div>
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 sm:hidden">{{ __('Entity type') }}</span>
                                <span class="text-sm text-zinc-400">{{ $client->entity_type ?: __('Not recorded') }}</span>
                            </div>
                            <div class="sm:text-right">
                                <flux:badge :color="$client->status->badgeColor()">
                                    {{ $client->status->label() }}
                                </flux:badge>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-14 text-center">
                            <p class="text-sm font-medium text-zinc-200">
                                {{ $search === '' ? __('No clients recorded') : __('No clients match this search') }}
                            </p>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">
                                {{ $search === ''
                                    ? __('Create the first canonical identity using the controlled form beside this register.')
                                    : __('Check the code or name and try a broader search.') }}
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($this->clients->hasPages())
                <div class="mt-6">
                    {{ $this->clients->links() }}
                </div>
            @endif
        </section>

        <aside aria-labelledby="create-client-heading" class="xl:sticky xl:top-8 xl:self-start">
            <div class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <div class="mb-6">
                    <span class="mb-4 grid size-10 place-items-center rounded-xl bg-amber-400 text-black">
                        <flux:icon.building-office-2 class="size-5" />
                    </span>
                    <h2 id="create-client-heading" class="text-lg font-semibold text-zinc-100">{{ __('Create client identity') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        {{ __('Only the identity foundation is captured in this packet. Names are stored exactly as entered.') }}
                    </p>
                </div>

                <form wire:submit="createClient" class="space-y-5">
                    <flux:input
                        wire:model="internalCode"
                        :label="__('Internal client code')"
                        placeholder="CL-0001"
                        maxlength="64"
                        autocomplete="off"
                        required
                    />

                    <flux:input
                        wire:model="legalName"
                        :label="__('Legal name')"
                        placeholder="Registered legal name"
                        maxlength="255"
                        autocomplete="organization"
                        required
                    />

                    <flux:input
                        wire:model="tradeName"
                        :label="__('Trade name')"
                        :description="__('Optional. Leave blank when it matches the legal name.')"
                        maxlength="255"
                        autocomplete="organization"
                    />

                    <flux:input
                        wire:model="entityType"
                        :label="__('Entity type')"
                        :description="__('Optional until the firm confirms its controlled classification list.')"
                        placeholder="Free zone company"
                        maxlength="100"
                    />

                    <flux:button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        wire:loading.attr="disabled"
                        wire:target="createClient"
                    >
                        <span wire:loading.remove wire:target="createClient">{{ __('Create client') }}</span>
                        <span wire:loading wire:target="createClient">{{ __('Creating client...') }}</span>
                    </flux:button>
                </form>
            </div>

            <p class="mt-4 px-1 text-xs leading-5 text-zinc-600">
                {{ __('Tax registrations, contacts, documents and compliance dates are intentionally excluded from this step.') }}
            </p>
        </aside>
    </div>
</div>
