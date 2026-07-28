<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="mb-3 text-sm font-medium text-amber-300">
                    {{ app(\App\Tenancy\FirmContext::class)->firm()->name }}
                </p>
                <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                    {{ __('Audit register') }}
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-zinc-400">
                    {{ __('Retained evidence of significant actions in this firm. This register is read only. Records cannot be edited or removed from here or anywhere else in the application.') }}
                </p>
            </div>
        </div>
    </header>

    <section class="mt-8" aria-labelledby="audit-filters-heading">
        <h2 id="audit-filters-heading" class="sr-only">{{ __('Filters') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :label="__('Search')"
                :placeholder="__('Action, record, correlation or reason')"
                icon="magnifying-glass"
            />

            <flux:select wire:model.live="action" :label="__('Action')">
                <flux:select.option value="">{{ __('All actions') }}</flux:select.option>
                @foreach ($this->actions as $recordedAction)
                    <flux:select.option :value="$recordedAction">{{ $recordedAction }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="fromDate" :label="__('From date')" />
            <flux:input type="date" wire:model.live="toDate" :label="__('To date')" />
        </div>

        <div class="mt-4 flex items-center justify-between gap-4">
            <p class="text-sm text-zinc-500">
                {{ trans_choice('{0} No records match these filters.|{1} :count retained record.|[2,*] :count retained records.', $this->records->total(), ['count' => $this->records->total()]) }}
            </p>
            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" wire:click="clearFilters">
                    {{ __('Clear filters') }}
                </flux:button>
                <flux:button
                    size="sm"
                    variant="filled"
                    icon="arrow-down-tray"
                    wire:click="exportRegister"
                    wire:loading.attr="disabled"
                    wire:target="exportRegister"
                >
                    <span wire:loading.remove wire:target="exportRegister">{{ __('Export matching records') }}</span>
                    <span wire:loading wire:target="exportRegister">{{ __('Exporting...') }}</span>
                </flux:button>
            </div>
        </div>
    </section>

    <section class="mt-6" aria-labelledby="audit-records-heading">
        <h2 id="audit-records-heading" class="sr-only">{{ __('Retained records') }}</h2>
        <div class="overflow-hidden rounded-2xl bg-zinc-900/70 ring-1 ring-white/8">
            <div class="divide-y divide-white/8">
                @forelse ($this->records as $record)
                    <article class="px-6 py-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge color="zinc">{{ $record->action }}</flux:badge>
                                    <span class="text-xs text-zinc-500">
                                        {{ $record->created_at?->timezone('Asia/Dubai')->format('d M Y H:i') }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-zinc-300">
                                    {{ __('Actor: :actor', [
                                        'actor' => $record->actor_id === null
                                            ? __('System')
                                            : ($this->actorNames[$record->actor_id] ?? __('Former member')),
                                    ]) }}
                                </p>

                                @if ($record->auditable_type)
                                    <p class="mt-1 truncate text-xs text-zinc-500">
                                        {{ class_basename($record->auditable_type) }} · {{ $record->auditable_id }}
                                    </p>
                                @endif

                                @if ($record->reason)
                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-400">
                                        {{ $record->reason }}
                                    </p>
                                @endif
                            </div>

                            <div class="shrink-0 lg:w-80">
                                @if ($record->before_values)
                                    <p class="text-xs font-medium text-zinc-500">{{ __('Before') }}</p>
                                    <dl class="mt-1 space-y-1">
                                        @foreach ($record->before_values as $key => $value)
                                            <div class="flex justify-between gap-3 text-xs">
                                                <dt class="text-zinc-500">{{ $key }}</dt>
                                                <dd class="truncate text-zinc-300">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif

                                @if ($record->after_values)
                                    <p class="mt-3 text-xs font-medium text-zinc-500">{{ __('After') }}</p>
                                    <dl class="mt-1 space-y-1">
                                        @foreach ($record->after_values as $key => $value)
                                            <div class="flex justify-between gap-3 text-xs">
                                                <dt class="text-zinc-500">{{ $key }}</dt>
                                                <dd class="truncate text-zinc-300">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-14 text-center">
                        <p class="text-sm font-medium text-zinc-200">{{ __('No retained records match') }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">
                            {{ __('Adjust the action, date range or search and try again.') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($this->records->hasPages())
            <div class="mt-6">
                {{ $this->records->links() }}
            </div>
        @endif
    </section>
</div>
