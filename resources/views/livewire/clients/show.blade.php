<div class="tbt-page">
    <nav aria-label="{{ __('Breadcrumb') }}" class="mb-6 flex items-center gap-2 text-sm text-zinc-500">
        <a href="{{ route('dashboard') }}" class="hover:text-zinc-200" wire:navigate>{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-300">{{ $this->client->internal_code }}</span>
    </nav>

    <header class="tbt-page-header">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <p class="font-mono text-sm font-medium text-amber-300">{{ $this->client->internal_code }}</p>
                    <flux:badge :color="$this->client->status->badgeColor()">{{ $this->client->status->label() }}</flux:badge>
                </div>
                <h1 class="tbt-page-title mt-3">{{ $this->client->legal_name }}</h1>
                <p class="tbt-page-copy">{{ $this->client->entity_type ?: __('Entity type not recorded') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" :href="route('schedule.index', ['clientId' => $this->client->id])" icon="calendar-days" wire:navigate>{{ __('Open calendar') }}</flux:button>
                <flux:button variant="filled" :href="route('obligations.index')" icon="clipboard-document-list" wire:navigate>{{ __('Open tax returns') }}</flux:button>
            </div>
        </div>
    </header>

    <div class="tbt-panel mt-5 overflow-x-auto p-1">
        <div class="flex min-w-max gap-1" role="tablist" aria-label="{{ __('Client workspace sections') }}">
            @foreach ([
                'overview' => __('Overview'),
                'vat' => __('VAT'),
                'corporate-tax' => __('Corporate Tax'),
                'documents' => __('Documents'),
                'people' => __('People'),
                'activity' => __('Activity'),
            ] as $value => $label)
                <button type="button" wire:click="$set('tab', '{{ $value }}')" @class([
                    'rounded-lg border px-4 py-2.5 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300',
                    'border-amber-300/50 bg-amber-300/15 text-amber-200' => $tab === $value,
                    'border-transparent text-zinc-500 hover:bg-[var(--tbt-row-hover)] hover:text-[var(--tbt-text)]' => $tab !== $value,
                ]) role="tab" aria-selected="{{ $tab === $value ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <section class="mt-8" aria-live="polite">
        @if ($tab === 'overview')
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
                <div class="tbt-panel">
                    <div class="tbt-panel-header"><h2 class="tbt-panel-title">{{ __('Registration and filing profile') }}</h2></div>
                    <dl class="divide-y divide-[var(--tbt-border)] px-5">
                        @foreach ([
                            __('Primary email') => $this->client->primary_email,
                            __('Primary phone') => $this->client->primary_phone,
                            __('VAT frequency') => $this->client->vat_frequency,
                            __('VAT TRN') => $this->client->vat_trn,
                            __('Corporate Tax TRN') => $this->client->corporate_tax_trn,
                            __('Trade licence authority') => $this->client->trade_license_authority,
                            __('Trade licence expiry') => $this->client->trade_license_expires_on?->format('d M Y'),
                        ] as $label => $value)
                            <div class="grid gap-1 py-4 sm:grid-cols-[14rem_minmax(0,1fr)]">
                                <dt class="text-sm text-zinc-500">{{ $label }}</dt>
                                <dd class="text-sm font-medium text-zinc-200">{{ $value ?: __('Not recorded') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
                <aside class="tbt-panel">
                    <div class="tbt-panel-header"><h2 class="tbt-panel-title">{{ __('Reminder settings') }}</h2></div>
                    <div class="divide-y divide-[var(--tbt-border)] px-5">
                        @foreach ([
                            __('Documents') => $this->client->document_reminder_mode,
                            __('VAT') => $this->client->vat_reminder_mode,
                            __('Corporate Tax') => $this->client->corporate_tax_reminder_mode,
                        ] as $label => $mode)
                            <div class="flex items-center justify-between gap-4 py-4">
                                <span class="text-sm text-zinc-400">{{ $label }}</span>
                                <flux:badge :color="$mode->value === 'automatic' ? 'green' : ($mode->value === 'review' ? 'amber' : 'zinc')">{{ $mode->label() }}</flux:badge>
                            </div>
                        @endforeach
                    </div>
                    <div class="m-5 rounded-xl bg-amber-300/[0.08] p-5 ring-1 ring-amber-300/25">
                        <p class="text-sm font-semibold text-amber-200">{{ __('Assisted setup available') }}</p>
                        <p class="mt-2 text-sm leading-6 text-zinc-400">{{ __('TBT can help prepare this client record from information you provide. Authority access should be supervised. FTA and UAE Pass passwords are never stored in this platform.') }}</p>
                    </div>
                </aside>
            </div>
        @elseif (in_array($tab, ['vat', 'corporate-tax'], true))
            @php($type = $tab === 'vat' ? 'VAT Return' : 'Corporate Tax Return')
            <div class="flex items-end justify-between gap-4">
                <div><h2 class="text-lg font-semibold text-zinc-100">{{ $type }}</h2><p class="mt-1 text-sm text-zinc-500">{{ __('Recorded periods and calculated filing deadlines for this client.') }}</p></div>
            </div>
            <div class="tbt-table-shell mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/8 text-xs text-zinc-500"><tr><th class="px-4 py-3">{{ __('Period') }}</th><th class="px-4 py-3">{{ __('Due date') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Work') }}</th></tr></thead>
                    <tbody class="divide-y divide-white/8">
                        @forelse ($this->client->obligations->where('obligation_type', $type) as $obligation)
                            <tr><td class="px-4 py-4 text-zinc-200">{{ $obligation->period_label ?: __('No period label') }}</td><td class="px-4 py-4 font-mono text-zinc-300">{{ $obligation->effectiveDueDate()->format('d M Y') }}</td><td class="px-4 py-4"><flux:badge :color="$obligation->status->badgeColor()">{{ $obligation->status->label() }}</flux:badge></td><td class="px-4 py-4 text-zinc-500">{{ $obligation->workItems->count() }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-12 text-center text-zinc-500">{{ __('No recorded periods for this category.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($tab === 'documents')
            <h2 class="text-lg font-semibold text-zinc-100">{{ __('Current documents') }}</h2>
            <div class="tbt-panel mt-5 divide-y divide-[var(--tbt-border)] px-5">
                @forelse ($this->client->documents->sortBy('expires_on') as $document)
                    <div class="grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_12rem_9rem] sm:items-center">
                        <div><p class="text-sm font-medium text-zinc-200">{{ $document->documentTypeVersion->name }}</p><p class="mt-1 text-xs text-zinc-500">{{ $document->person?->name ?? __('Client document') }}</p></div>
                        <p class="font-mono text-sm text-zinc-300">{{ $document->expires_on?->format('d M Y') ?: __('No expiry') }}</p>
                        <flux:badge :color="$document->expires_on?->isPast() ? 'red' : 'amber'">{{ $document->expires_on?->isPast() ? __('Expired') : __('Current') }}</flux:badge>
                    </div>
                @empty
                    <p class="py-12 text-center text-sm text-zinc-500">{{ __('No current document records.') }}</p>
                @endforelse
            </div>
        @elseif ($tab === 'people')
            <h2 class="text-lg font-semibold text-zinc-100">{{ __('People and identity documents') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @forelse ($this->client->people as $person)
                    <article class="tbt-panel p-5">
                        <div class="flex items-start justify-between gap-4"><div><h3 class="text-sm font-semibold text-zinc-100">{{ $person->name }}</h3><p class="mt-1 text-xs text-zinc-500">{{ str($person->role)->replace('_', ' ')->title() }}</p></div><flux:badge :color="$person->is_active ? 'green' : 'zinc'">{{ $person->is_active ? __('Active') : __('Inactive') }}</flux:badge></div>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Passport expiry') }}</dt><dd class="font-mono text-zinc-300">{{ $person->passport_expires_on?->format('d M Y') ?: __('Not recorded') }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Emirates ID expiry') }}</dt><dd class="font-mono text-zinc-300">{{ $person->emirates_id_expires_on?->format('d M Y') ?: __('Not recorded') }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('No people recorded for this client.') }}</p>
                @endforelse
            </div>
        @else
            <h2 class="text-lg font-semibold text-zinc-100">{{ __('Client activity') }}</h2>
            <ol class="tbt-panel mt-5 divide-y divide-[var(--tbt-border)] px-5">
                @forelse ($this->activity as $event)
                    <li class="grid gap-2 py-4 sm:grid-cols-[11rem_minmax(0,1fr)]"><time class="font-mono text-xs text-zinc-500">{{ $event->created_at?->format('d M Y, H:i') }}</time><div><p class="text-sm font-medium text-zinc-200">{{ str($event->action)->replace('.', ' ')->title() }}</p><p class="mt-1 text-xs text-zinc-500">{{ $event->reason ?: __('Recorded in the firm audit history.') }}</p></div></li>
                @empty
                    <li class="py-12 text-center text-sm text-zinc-500">{{ __('No client activity recorded.') }}</li>
                @endforelse
            </ol>
        @endif
    </section>
</div>
