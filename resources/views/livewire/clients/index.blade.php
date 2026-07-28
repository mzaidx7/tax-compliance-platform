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
                    {{ __('Create the canonical client identity, then maintain explicit service ownership, tax registrations and actual periods.') }}
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
                <div class="hidden grid-cols-[8rem_minmax(0,1.4fr)_minmax(0,1fr)_7rem_8rem] gap-4 border-b border-white/8 px-4 py-3 text-xs font-medium text-zinc-500 sm:grid">
                    <span>{{ __('Code') }}</span>
                    <span>{{ __('Legal identity') }}</span>
                    <span>{{ __('Entity type') }}</span>
                    <span class="text-right">{{ __('Status') }}</span>
                    <span class="text-right">{{ __('Profile') }}</span>
                </div>

                <div class="divide-y divide-white/8">
                    @forelse ($this->clients as $client)
                        <article
                            wire:key="client-{{ $client->id }}"
                            class="grid gap-4 px-4 py-5 transition-colors duration-150 hover:bg-white/[0.025] sm:grid-cols-[8rem_minmax(0,1.4fr)_minmax(0,1fr)_7rem_8rem] sm:items-center"
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
                            <div class="sm:text-right">
                                <p class="mb-2 text-xs text-zinc-500">
                                    {{ trans_choice('{0} No services|{1} 1 service|[2,*] :count services', $client->service_enrollments_count, ['count' => $client->service_enrollments_count]) }}
                                    ·
                                    {{ trans_choice('{0} No registrations|{1} 1 registration|[2,*] :count registrations', $client->tax_registrations_count, ['count' => $client->tax_registrations_count]) }}
                                </p>
                                <flux:button size="sm" variant="ghost" wire:click="openProfile('{{ $client->id }}')">
                                    {{ __('Manage') }}
                                </flux:button>
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
                {{ __('This step captures identity only. Service and tax profile records are maintained from the client register.') }}
            </p>
        </aside>
    </div>

    <flux:modal wire:model.self="showProfileModal" class="md:w-[64rem]">
        <div class="space-y-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Client service and tax profile') }}</flux:heading>
                    <flux:text class="mt-2">{{ $selectedClientLabel }}</flux:text>
                </div>
                <flux:button variant="ghost" icon="x-mark" wire:click="closeProfile">{{ __('Close') }}</flux:button>
            </div>

            @if ($this->selectedClient)
                <div class="grid gap-8 lg:grid-cols-2">
                    <section aria-labelledby="service-enrollment-heading">
                        <h3 id="service-enrollment-heading" class="text-sm font-semibold text-zinc-100">{{ __('Service enrollments') }}</h3>
                        <div class="mt-3 divide-y divide-white/8 border-y border-white/8">
                            @forelse ($this->selectedClient->serviceEnrollments as $enrollment)
                                <div class="py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-medium text-zinc-200">{{ $enrollment->service->label() }}</p>
                                        <flux:badge color="green">{{ $enrollment->status->label() }}</flux:badge>
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-500">
                                        {{ __('Responsible: :name · From :date', [
                                            'name' => $enrollment->responsibleMembership->user->name,
                                            'date' => $enrollment->starts_on->format('d M Y'),
                                        ]) }}
                                    </p>
                                </div>
                            @empty
                                <p class="py-5 text-sm text-zinc-500">{{ __('No service enrollment is recorded.') }}</p>
                            @endforelse
                        </div>

                        <form wire:submit="addService" class="mt-5 grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model="service" :label="__('Service')" required>
                                @foreach ($this->services as $serviceOption)
                                    <flux:select.option :value="$serviceOption->value">{{ $serviceOption->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="responsibleMembershipId" :label="__('Responsible member')" required>
                                <flux:select.option value="">{{ __('Select member') }}</flux:select.option>
                                @foreach ($this->responsibleMembers as $member)
                                    <flux:select.option :value="$member->id">{{ $member->user->name }} · {{ $member->role->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input type="date" wire:model="serviceStartsOn" :label="__('Starts on')" required />
                            <flux:input type="date" wire:model="serviceEndsOn" :label="__('Ends on')" />
                            <flux:button type="submit" variant="filled" class="sm:col-span-2">{{ __('Add service enrollment') }}</flux:button>
                        </form>
                    </section>

                    <section aria-labelledby="tax-registration-heading">
                        <h3 id="tax-registration-heading" class="text-sm font-semibold text-zinc-100">{{ __('Tax registrations and actual periods') }}</h3>
                        <div class="mt-3 space-y-3">
                            @forelse ($this->selectedClient->taxRegistrations as $registration)
                                <div class="rounded-xl bg-zinc-900/70 p-4 ring-1 ring-white/8">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-medium text-zinc-200">{{ $registration->tax_type->label() }}</p>
                                        <flux:badge color="zinc">{{ $registration->status->label() }}</flux:badge>
                                    </div>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $registration->registration_number }}</p>
                                    <ul class="mt-3 space-y-1">
                                        @forelse ($registration->periods as $period)
                                            <li class="text-xs text-zinc-400">
                                                {{ $period->label }} · {{ $period->starts_on->format('d M Y') }} to {{ $period->ends_on->format('d M Y') }}
                                            </li>
                                        @empty
                                            <li class="text-xs text-zinc-500">{{ __('No actual periods recorded.') }}</li>
                                        @endforelse
                                    </ul>
                                </div>
                            @empty
                                <p class="py-5 text-sm text-zinc-500">{{ __('No tax registration is recorded.') }}</p>
                            @endforelse
                        </div>

                        <form wire:submit="addRegistration" class="mt-5 grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model="taxType" :label="__('Tax type')" required>
                                @foreach ($this->taxTypes as $taxTypeOption)
                                    <flux:select.option :value="$taxTypeOption->value">{{ $taxTypeOption->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="registrationStatus" :label="__('Status')" required>
                                @foreach ($this->registrationStatuses as $statusOption)
                                    <flux:select.option :value="$statusOption->value">{{ $statusOption->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="registrationNumber" :label="__('Registration number')" maxlength="64" required />
                            <flux:input type="date" wire:model="registrationEffectiveFrom" :label="__('Effective from')" />
                            <flux:input type="date" wire:model="registrationEffectiveTo" :label="__('Effective to')" />
                            <flux:button type="submit" variant="filled" class="sm:col-span-2">{{ __('Add tax registration') }}</flux:button>
                        </form>

                        @if ($this->selectedClient->taxRegistrations->isNotEmpty())
                            <form wire:submit="addPeriod" class="mt-6 grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model="periodRegistrationId" :label="__('Registration')" required>
                                    <flux:select.option value="">{{ __('Select registration') }}</flux:select.option>
                                    @foreach ($this->selectedClient->taxRegistrations as $registration)
                                        <flux:select.option :value="$registration->id">{{ $registration->tax_type->label() }} · {{ $registration->registration_number }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="periodLabel" :label="__('Actual period label')" maxlength="100" required />
                                <flux:input type="date" wire:model="periodStartsOn" :label="__('Starts on')" required />
                                <flux:input type="date" wire:model="periodEndsOn" :label="__('Ends on')" required />
                                <flux:button type="submit" variant="ghost" class="sm:col-span-2">{{ __('Add actual tax period') }}</flux:button>
                            </form>
                        @endif
                    </section>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
