<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <p class="mb-3 text-sm font-medium text-amber-300">{{ app(\App\Tenancy\FirmContext::class)->firm()->name }}</p>
        <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Operational reports') }}</h1>
        <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">{{ __('Firm-scoped schedules, periods, document-expiry metadata and workload state. Reports use stored records only and do not calculate statutory dates, tax, risk or compliance.') }}</p>
    </header>

    <section class="mt-8" aria-labelledby="report-controls-heading">
        <h2 id="report-controls-heading" class="sr-only">{{ __('Report controls') }}</h2>
        <div class="grid gap-4 border-y border-white/8 py-5 sm:grid-cols-[minmax(14rem,1fr)_12rem_auto] sm:items-end">
            <flux:select wire:model.live="reportType" :label="__('Report')">
                @foreach ($this->reportTypes() as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="month" type="month" :label="__('Report month')" />
            <flux:button variant="filled" icon="arrow-down-tray" wire:click="exportReport">{{ __('Export CSV') }}</flux:button>
        </div>
    </section>

    <section class="mt-8" aria-labelledby="report-results-heading">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 id="report-results-heading" class="text-lg font-semibold text-zinc-100">{{ \App\Enums\OperationalReportType::from($reportType)->label() }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Visible columns exactly match the export definition. Up to 100 rows are previewed.') }}</p>
            </div>
            <span class="text-xs text-zinc-500">{{ trans_choice('{0} No preview rows|{1} 1 preview row|[2,*] :count preview rows', count($this->report['rows']), ['count' => count($this->report['rows'])]) }}</span>
        </div>

        <div class="overflow-x-auto border-y border-white/8" tabindex="0" aria-label="{{ __('Scrollable report table') }}">
            <table class="min-w-full divide-y divide-white/8 text-left text-sm">
                <thead>
                    <tr>
                        @foreach ($this->report['headers'] as $header)
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-xs font-semibold text-zinc-400">{{ str($header)->replace('_', ' ')->title() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/8">
                    @forelse ($this->report['rows'] as $row)
                        <tr>
                            @foreach ($row as $value)
                                <td class="max-w-72 whitespace-nowrap px-4 py-3 text-zinc-300">{{ $value === null || $value === '' ? __('Not recorded') : (is_bool($value) ? ($value ? __('Yes') : __('No')) : $value) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($this->report['headers']) }}" class="px-6 py-14 text-center text-sm text-zinc-500">{{ __('No stored records match this report month.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->report['truncated'])
            <p class="mt-3 text-sm text-amber-300">{{ __('The preview is limited to 100 rows. Export the report for the complete bounded dataset.') }}</p>
        @endif
    </section>
</div>
