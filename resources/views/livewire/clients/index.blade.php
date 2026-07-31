{{--
THESIS: Client identity begins as a controlled firm ledger, not a collection of profile cards.
OWN-WORLD: Matte ink surfaces, silver hierarchy, and restrained gold creation cues.
STORY: The administrator scans the current register, searches identity records, and adds one canonical client.
FIRST VIEWPORT: The searchable register leads while the creation panel remains visible beside it on wide screens.
FORM: Continuous identity ledger with a compact creation station.
--}}
<div class="tbt-page">
    <header class="tbt-page-header">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="tbt-page-kicker">{{ $this->currentFirmName }}</p>
                <h1 class="tbt-page-title">
                    {{ __('Clients') }}
                </h1>
                <p class="tbt-page-copy">
                    {{ __("Keep each client's contact details, Tax Registration Numbers, Tax Periods, documents and assigned team in one place.") }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <flux:button
                    variant="primary"
                    icon="arrow-down-tray"
                    :href="route('clients.import-template', 'workbook')"
                >
                    {{ __('Download Excel template') }}
                </flux:button>
                <flux:button variant="ghost" icon="arrow-up-tray" wire:click="openImport">
                    {{ __('Import completed file') }}
                </flux:button>
                <flux:button variant="ghost" icon="arrow-down-tray" wire:click="exportMasterData">
                    {{ __('Download client data') }}
                </flux:button>
                <flux:badge color="amber" icon="shield-check">{{ __('Administrator access required') }}</flux:badge>
            </div>
        </div>
    </header>

    <section class="tbt-import-steps mt-5" aria-labelledby="bulk-import-heading">
        <div class="tbt-import-steps__intro">
            <p class="tbt-page-kicker">{{ __('Bulk client setup') }}</p>
            <h2 id="bulk-import-heading">{{ __('Add clients and people in one upload') }}</h2>
            <p>{{ __('The workbook includes separate Clients and People sheets, examples, date guidance and protected identifier columns.') }}</p>
        </div>
        <ol class="tbt-import-steps__list">
            <li><span>1</span><strong>{{ __('Download') }}</strong><small>{{ __('Use the Excel template') }}</small></li>
            <li><span>2</span><strong>{{ __('Complete') }}</strong><small>{{ __('Fill Clients and People') }}</small></li>
            <li><span>3</span><strong>{{ __('Import') }}</strong><small>{{ __('Preview before saving') }}</small></li>
        </ol>
    </section>

    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1fr)_23rem]">
        <section aria-labelledby="client-register-heading">
            <div class="tbt-section-heading">
                <div>
                    <h2 id="client-register-heading">{{ __('Client list') }}</h2>
                    <p>{{ __('Each client code must be unique within your firm.') }}</p>
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

            <div class="tbt-table-shell">
                <div class="hidden grid-cols-[6rem_minmax(0,1.4fr)_minmax(0,.8fr)_6rem_11rem] gap-4 border-b border-[var(--tbt-border)] bg-[var(--tbt-table-head)] px-4 py-3 text-xs font-semibold text-[var(--tbt-muted-strong)] lg:grid">
                    <span>{{ __('Code') }}</span>
                    <span>{{ __('Client name') }}</span>
                    <span>{{ __('Entity type') }}</span>
                    <span class="text-right">{{ __('Status') }}</span>
                    <span class="text-right">{{ __('Actions') }}</span>
                </div>

                <div class="divide-y divide-[var(--tbt-border)]">
                    @forelse ($this->clients as $client)
                        <article
                            wire:key="client-{{ $client->id }}"
                            class="grid gap-4 px-4 py-5 transition-colors duration-150 hover:bg-[var(--tbt-row-hover)] lg:grid-cols-[6rem_minmax(0,1.4fr)_minmax(0,.8fr)_6rem_11rem] lg:items-center"
                        >
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Code') }}</span>
                                <span class="font-medium text-zinc-200">{{ $client->internal_code }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Client name') }}</span>
                                <p class="truncate text-sm font-medium text-zinc-100">{{ $client->legal_name }}</p>
                                @if ($client->trade_name)
                                    <p class="mt-1 truncate text-sm text-zinc-500">{{ $client->trade_name }}</p>
                                @endif
                            </div>
                            <div>
                                <span class="mb-1 block text-xs text-zinc-500 lg:hidden">{{ __('Entity type') }}</span>
                                <span class="text-sm text-zinc-400">{{ $client->entity_type ?: __('Not recorded') }}</span>
                            </div>
                            <div class="lg:text-right">
                                <flux:badge :color="$client->status->badgeColor()">
                                    {{ $client->status->label() }}
                                </flux:badge>
                            </div>
                            <div class="lg:text-right">
                                <p class="mb-2 text-xs leading-5 text-zinc-500">
                                    {{ trans_choice('{0} No services|{1} 1 service|[2,*] :count services', $client->service_enrollments_count, ['count' => $client->service_enrollments_count]) }}
                                    ·
                                    {{ trans_choice('{0} No registrations|{1} 1 registration|[2,*] :count registrations', $client->tax_registrations_count, ['count' => $client->tax_registrations_count]) }}
                                </p>
                                <div class="flex flex-wrap gap-2 lg:justify-end">
                                    <flux:button size="sm" variant="ghost" :href="route('clients.show', $client)" wire:navigate>
                                        {{ __('Open workspace') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="openProfile('{{ $client->id }}')">
                                        {{ __('Manage') }}
                                    </flux:button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-14 text-center">
                            <p class="text-sm font-medium text-zinc-200">
                                {{ $search === '' ? __('No clients recorded') : __('No clients match this search') }}
                            </p>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">
                                {{ $search === ''
                                    ? __('Add your first client using the form beside this list.')
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

        <aside aria-labelledby="create-client-heading" class="2xl:sticky 2xl:top-8 2xl:self-start">
            <div class="tbt-panel p-6">
                <div class="mb-6">
                    <span class="mb-4 grid size-10 place-items-center rounded-xl bg-amber-400 text-black">
                        <flux:icon.building-office-2 class="size-5" />
                    </span>
                    <h2 id="create-client-heading" class="text-lg font-semibold text-zinc-100">{{ __('Add a client') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                        {{ __('For a full setup, import the client master file with tax periods and document expiry dates. You can still add a client here.') }}
                    </p>
                </div>

                <form wire:submit="createClient" class="space-y-5">
                    <flux:input
                        wire:model="internalCode"
                        :label="__('Client code')"
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
                        :description="__('Optional. Record the legal form, such as LLC, FZ-LLC or branch.')"
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
                {{ __('Imported VAT and Corporate Tax periods create the next filing deadlines automatically.') }}
            </p>
        </aside>
    </div>

    <flux:modal wire:model.self="showImportModal" class="w-[calc(100vw-2rem)]! max-w-5xl!">
        <div class="space-y-7">
            <div>
                <flux:heading size="lg">{{ __('Import client master data') }}</flux:heading>
                <flux:text class="mt-2 max-w-2xl">
                    {{ __('Upload the TBT workbook or a client CSV containing client, tax, contact and document details. Review every row before saving. The uploaded file is not kept.') }}
                </flux:text>
            </div>

            <section class="rounded-2xl bg-amber-300/[0.06] p-5 ring-1 ring-amber-300/20" aria-labelledby="import-template-heading">
                <h3 id="import-template-heading" class="text-sm font-semibold text-amber-200">{{ __('Start with a clean template') }}</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-400">{{ __('Use the workbook for Clients and People together, or download separate CSV files. Never add FTA or UAE Pass passwords.') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button size="sm" variant="filled" icon="arrow-down-tray" :href="route('clients.import-template', 'workbook')">{{ __('TBT Client Master.xlsx') }}</flux:button>
                    <flux:button size="sm" variant="ghost" :href="route('clients.import-template', 'clients')">{{ __('Clients.csv') }}</flux:button>
                    <flux:button size="sm" variant="ghost" :href="route('clients.import-template', 'people')">{{ __('People.csv') }}</flux:button>
                </div>
            </section>

            <div class="grid gap-5 border-y border-white/8 py-6 md:grid-cols-2 md:items-start">
                <flux:field>
                    <flux:label>{{ __('Client master workbook or CSV') }}</flux:label>
                    <input
                        wire:model="clientImportFile"
                        type="file"
                        accept=".csv,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        class="release-file-input mt-2 block min-h-11 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-300 focus:outline-none focus:ring-2 focus:ring-amber-300"
                    />
                    <flux:description>{{ __('Maximum 500 rows and 2 MB. Required: internal_code, legal_name. Optional: email, mobile, vat_trn, ct_trn, vat_frequency, VAT and Corporate Tax period dates, licence, passport and Emirates ID details. Dates use YYYY-MM-DD.') }}</flux:description>
                    <flux:error name="clientImportFile" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('People CSV, optional') }}</flux:label>
                    <input
                        wire:model="peopleImportFile"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        class="release-file-input mt-2 block min-h-11 w-full rounded-xl border border-white/10 bg-zinc-950 px-3 py-2 text-sm text-zinc-300 focus:outline-none focus:ring-2 focus:ring-amber-300"
                    />
                    <flux:description>{{ __('Use this with Clients.csv. Each person must match client_internal_code in the client file.') }}</flux:description>
                    <flux:error name="peopleImportFile" />
                </flux:field>
                <flux:button
                    variant="primary"
                    class="md:col-span-2 md:justify-self-end"
                    wire:click="previewClientImport"
                    wire:loading.attr="disabled"
                    wire:target="clientImportFile,peopleImportFile,previewClientImport"
                >
                    <span wire:loading.remove wire:target="previewClientImport">{{ __('Validate and preview') }}</span>
                    <span wire:loading wire:target="previewClientImport">{{ __('Validating...') }}</span>
                </flux:button>
            </div>

            @if ($clientImportRows !== [])
                <section aria-labelledby="import-reconciliation-heading">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 id="import-reconciliation-heading" class="text-sm font-semibold text-zinc-100">{{ __('Import reconciliation') }}</h3>
                            <p class="mt-1 text-sm text-zinc-500">{{ __('Accepted and rejected counts always reconcile to the previewed rows.') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <flux:badge color="green">{{ $clientImportAccepted }} {{ __('accepted') }}</flux:badge>
                            <flux:badge :color="$clientImportRejected > 0 ? 'red' : 'zinc'">{{ $clientImportRejected }} {{ __('rejected') }}</flux:badge>
                            @if ($clientImportPeople !== [])
                                <flux:badge color="green">{{ $clientImportPeopleAccepted }} {{ __('people ready') }}</flux:badge>
                                <flux:badge :color="$clientImportPeopleRejected > 0 ? 'red' : 'zinc'">{{ $clientImportPeopleRejected }} {{ __('people rejected') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 max-h-80 overflow-auto border-y border-white/8">
                        <table class="min-w-full divide-y divide-white/8 text-left text-sm">
                            <thead class="sticky top-0 bg-zinc-900">
                                <tr>
                                    <th class="px-3 py-3 text-xs font-semibold text-zinc-400">{{ __('Line') }}</th>
                                    <th class="px-3 py-3 text-xs font-semibold text-zinc-400">{{ __('Code') }}</th>
                                    <th class="px-3 py-3 text-xs font-semibold text-zinc-400">{{ __('Legal name') }}</th>
                                    <th class="px-3 py-3 text-xs font-semibold text-zinc-400">{{ __('Result') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/8">
                                @foreach ($clientImportRows as $row)
                                    <tr wire:key="import-row-{{ $row['line'] }}">
                                        <td class="px-3 py-3 text-zinc-500">{{ $row['line'] }}</td>
                                        <td class="px-3 py-3 font-medium text-zinc-200">{{ $row['internalCode'] ?: __('Missing') }}</td>
                                        <td class="px-3 py-3 text-zinc-300">{{ $row['legalName'] ?: __('Missing') }}</td>
                                        <td class="px-3 py-3">
                                            @if ($row['valid'])
                                                <span class="text-green-300">{{ __('Ready') }}</span>
                                            @else
                                                <ul class="space-y-1 text-xs leading-5 text-red-300">
                                                    @foreach ($row['errors'] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="$set('showImportModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button
                    variant="primary"
                    wire:click="commitClientImport"
                    :disabled="$clientImportRows === [] || $clientImportRejected > 0 || $clientImportPeopleRejected > 0"
                    wire:loading.attr="disabled"
                    wire:target="commitClientImport"
                >
                    <span wire:loading.remove wire:target="commitClientImport">{{ __('Commit accepted clients') }}</span>
                    <span wire:loading wire:target="commitClientImport">{{ __('Committing import...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

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
                <section aria-labelledby="client-contacts-heading" class="grid gap-6 border-y border-white/8 py-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 id="client-contacts-heading" class="text-sm font-semibold text-zinc-100">{{ __('Contacts') }}</h3>
                                <p class="mt-1 text-xs text-zinc-500">{{ __('Purpose and preferred channel are stored explicitly.') }}</p>
                            </div>
                            <span class="text-xs text-zinc-500">{{ $this->selectedClient->contacts->count() }} {{ __('recorded') }}</span>
                        </div>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @forelse ($this->selectedClient->contacts as $contact)
                                <div class="rounded-xl bg-white/[0.025] p-3 ring-1 ring-white/8">
                                    <p class="text-sm font-medium text-zinc-200">{{ $contact->name }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $contact->purpose->label() }} / {{ $contact->preferred_channel->label() }}</p>
                                    <p class="mt-2 text-xs text-zinc-400">
                                        {{ $contact->preferred_channel->value === 'email' ? $contact->email : $contact->phone }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm text-zinc-500">{{ __('No client contacts recorded.') }}</p>
                            @endforelse
                        </div>

                        <form wire:submit="addContact" class="mt-5 grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="contactName" :label="__('Contact name')" maxlength="255" required />
                            <flux:input wire:model="contactPosition" :label="__('Position')" maxlength="100" />
                            <flux:select wire:model="contactPurpose" :label="__('Purpose')" required>
                                @foreach ($this->contactPurposes as $purpose)
                                    <flux:select.option :value="$purpose->value">{{ $purpose->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="contactPreferredChannel" :label="__('Preferred channel')" required>
                                @foreach ($this->contactChannels as $channel)
                                    <flux:select.option :value="$channel->value">{{ $channel->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input type="email" wire:model="contactEmail" :label="__('Email')" autocomplete="email" />
                            <flux:input wire:model="contactPhone" :label="__('Phone')" autocomplete="tel" maxlength="32" />
                            <flux:button type="submit" variant="filled" class="sm:col-span-2">{{ __('Add contact') }}</flux:button>
                        </form>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100">{{ __('Client lifecycle') }}</h3>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Every status change requires a reason and is retained.') }}</p>
                        <form wire:submit="transitionClient" class="mt-4 space-y-4">
                            <flux:select wire:model="clientStatus" :label="__('New client status')" required>
                                @foreach ($this->clientStatuses as $status)
                                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:textarea wire:model="clientStatusReason" :label="__('Reason')" maxlength="500" required />
                            <flux:button type="submit" variant="ghost" class="w-full">{{ __('Record status change') }}</flux:button>
                        </form>

                        <ol class="mt-5 space-y-2">
                            @forelse ($this->selectedClient->statusChanges->sortByDesc('changed_at') as $change)
                                <li class="text-xs leading-5 text-zinc-500">
                                    <span class="text-zinc-300">{{ $change->previous_status->label() }}</span>
                                    {{ __('to') }}
                                    <span class="text-zinc-300">{{ $change->new_status->label() }}</span>
                                    / {{ $change->actor->name }} / {{ $change->changed_at->format('d M Y') }}
                                </li>
                            @empty
                                <li class="text-xs text-zinc-500">{{ __('No lifecycle changes recorded.') }}</li>
                            @endforelse
                        </ol>
                    </div>
                </section>

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

                        @if ($this->selectedClient->serviceEnrollments->where('status', '!=', \App\Enums\ServiceEnrollmentStatus::Ended)->isNotEmpty())
                            <form wire:submit="transitionService" class="mt-6 grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model="serviceEnrollmentId" :label="__('Service enrollment')" required>
                                    <flux:select.option value="">{{ __('Select service') }}</flux:select.option>
                                    @foreach ($this->selectedClient->serviceEnrollments as $enrollment)
                                        @if ($enrollment->status !== \App\Enums\ServiceEnrollmentStatus::Ended)
                                            <flux:select.option :value="$enrollment->id">{{ $enrollment->service->label() }} / {{ $enrollment->status->label() }}</flux:select.option>
                                        @endif
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="serviceStatus" :label="__('New status')" required>
                                    @foreach ($this->serviceStatuses as $status)
                                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input type="date" wire:model="serviceStatusEffectiveOn" :label="__('Effective on')" required />
                                <flux:textarea wire:model="serviceStatusReason" :label="__('Reason')" maxlength="500" required />
                                <flux:button type="submit" variant="ghost" class="sm:col-span-2">{{ __('Record service status') }}</flux:button>
                            </form>
                        @endif
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
