{{-- Metadata only. This interface never accepts or stores a document file. --}}
<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="mb-3 text-sm font-medium text-amber-300">{{ __('Client records') }}</p>
                <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                    {{ __('Document expiry') }}
                </h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">
                    {{ __('Record document expiry dates and follow up before renewals are due. The platform stores the details entered by your team and does not confirm whether a document is legally valid.') }}
                </p>
            </div>
            <flux:badge color="amber" icon="calendar-days">{{ __('Metadata only') }}</flux:badge>
        </div>
    </header>

    <div class="mt-9 grid gap-8 xl:grid-cols-[minmax(0,1fr)_23rem]">
        <section aria-labelledby="expiry-register-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="expiry-register-heading" class="text-lg font-semibold text-zinc-100">{{ __('Current documents') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('When a document is renewed, the previous record remains in the history.') }}</p>
                </div>
                <span class="text-xs text-zinc-500">{{ $this->currentDocuments->count() }} {{ __('current records') }}</span>
            </div>

            <div class="mt-5 divide-y divide-white/8 border-y border-white/8">
                @forelse ($this->currentDocuments as $document)
                    @php
                        $days = $document->expires_on ? today()->diffInDays($document->expires_on, false) : null;
                    @endphp
                    <article class="grid gap-3 px-4 py-5 sm:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_9rem] sm:items-center">
                        <div>
                            <p class="text-sm font-medium text-zinc-100">{{ $document->client->legal_name }}</p>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $document->documentTypeVersion->name }} v{{ $document->documentTypeVersion->version }}
                                @if ($document->reference_label)
                                    / {{ $document->reference_label }}
                                @endif
                            </p>
                        </div>
                        <div class="text-sm text-zinc-400">
                            @if ($document->expires_on)
                                {{ __('Expires :date', ['date' => $document->expires_on->format('d M Y')]) }}
                            @else
                                {{ __('No expiry recorded') }}
                            @endif
                            @if ($document->expiryReminders->isNotEmpty())
                                @php $latestReminder = $document->expiryReminders->sortByDesc('scheduled_for')->first(); @endphp
                                <p class="mt-1 text-xs text-zinc-500">
                                    {{ __('Latest reminder: :kind on :date', [
                                        'kind' => $latestReminder->kind->label(),
                                        'date' => $latestReminder->scheduled_for->format('d M Y'),
                                    ]) }}
                                </p>
                            @endif
                        </div>
                        <div class="sm:text-right">
                            @if ($days === null)
                                <flux:badge color="zinc">{{ __('No expiry') }}</flux:badge>
                            @elseif ($days < 0)
                                <flux:badge color="red">{{ trans_choice('{1} 1 day overdue|[2,*] :count days overdue', abs((int) $days), ['count' => abs((int) $days)]) }}</flux:badge>
                            @elseif ($days === 0.0)
                                <flux:badge color="amber">{{ __('Expires today') }}</flux:badge>
                            @elseif ($days <= 90)
                                <flux:badge color="amber">{{ trans_choice('{1} 1 day left|[2,*] :count days left', (int) $days, ['count' => (int) $days]) }}</flux:badge>
                            @else
                                <flux:badge color="green">{{ __('Future expiry') }}</flux:badge>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-14 text-center">
                        <p class="text-sm font-medium text-zinc-200">{{ __('No documents recorded') }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">{{ __('Create a document type, then add the document details supplied by the client.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-6">
            <section aria-labelledby="record-document-heading" class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 id="record-document-heading" class="text-lg font-semibold text-zinc-100">{{ __('Add document details') }}</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Dates are stored exactly as supplied. Select a prior record only for a renewal.') }}</p>
                <form wire:submit="recordDocument" class="mt-5 space-y-4">
                    <flux:select wire:model="clientId" :label="__('Client')" required>
                        <flux:select.option value="">{{ __('Select client') }}</flux:select.option>
                        @foreach ($this->clients as $client)
                            <flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="documentTypeVersionId" :label="__('Published document type')" required>
                        <flux:select.option value="">{{ __('Select type') }}</flux:select.option>
                        @foreach ($this->latestDocumentTypes as $type)
                            <flux:select.option :value="$type->id">{{ $type->name }} v{{ $type->version }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="supersedesClientDocumentId" :label="__('Renews current record')">
                        <flux:select.option value="">{{ __('New record') }}</flux:select.option>
                        @foreach ($this->currentDocuments as $document)
                            <flux:select.option :value="$document->id">{{ $document->client->internal_code }} / {{ $document->documentTypeVersion->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="referenceLabel" :label="__('Reference label')" maxlength="100" />
                    <flux:input type="date" wire:model="issuedOn" :label="__('Issued on')" />
                    <flux:input type="date" wire:model="expiresOn" :label="__('Expires on')" />
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Save document details') }}</flux:button>
                </form>
            </section>

            <section aria-labelledby="publish-type-heading" class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <h2 id="publish-type-heading" class="text-lg font-semibold text-zinc-100">{{ __('Publish document type') }}</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Changing an existing document type creates a new version and keeps the previous version in the history.') }}</p>
                <form wire:submit="publishType" class="mt-5 space-y-4">
                    <flux:input wire:model="typeKey" :label="__('Stable key')" placeholder="trade_licence" maxlength="64" required />
                    <flux:input wire:model="typeName" :label="__('Display name')" placeholder="Trade licence" maxlength="100" required />
                    <flux:checkbox wire:model="expiryRequired" :label="__('Expiry date required')" />
                    <flux:input wire:model="reminderDays" :label="__('Reminder days before expiry')" description="Comma-separated, such as 90, 30, 7, 1" />
                    <flux:input type="number" min="1" max="365" wire:model="overdueRepeatDays" :label="__('Repeat overdue every days')" />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Publish version') }}</flux:button>
                </form>
            </section>
        </aside>
    </div>
</div>
