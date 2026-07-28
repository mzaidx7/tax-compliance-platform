<div class="mx-auto w-full max-w-7xl">
    <header class="border-b border-white/8 pb-8">
        <p class="mb-3 text-sm font-medium text-amber-300">{{ __('E-invoicing readiness') }}</p>
        <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Invoice transaction samples') }}</h1>
        <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">{{ __('Retain synthetic sample fields and manually identified invoice-data issues. Values are never calculated, corrected, transmitted or combined with party readiness.') }}</p>
    </header>

    <div class="mt-9 grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section>
            <h2 class="text-lg font-semibold text-zinc-100">{{ __('Sample register') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Every displayed value is supplied evidence with a retained source reference.') }}</p>
            <div class="mt-5 space-y-5">
                @forelse ($this->samples as $sample)
                    <article class="rounded-2xl bg-zinc-900/50 p-5 ring-1 ring-white/8">
                        <p class="text-sm font-semibold text-zinc-100">{{ $sample->sample_reference }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ $sample->client->internal_code }} / {{ $sample->client->legal_name }}</p>
                        <div class="mt-5 divide-y divide-white/8 border-y border-white/8">
                            @foreach ($sample->fields as $field)
                                <div class="grid gap-2 py-3 sm:grid-cols-[11rem_minmax(0,1fr)]">
                                    <span class="text-xs font-medium text-zinc-400">{{ $field->field_key->label() }}</span>
                                    <span class="text-sm text-zinc-200">{{ $field->supplied_value }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if ($sample->issues->isNotEmpty())
                            <div class="mt-5 space-y-3">
                                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ __('Explainable invoice issues') }}</p>
                                @foreach ($sample->issues as $issue)
                                    <div class="rounded-xl bg-zinc-950/60 px-4 py-3 ring-1 ring-white/8">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-medium text-zinc-200">{{ $issue->ruleVersion->definition->name }}</p>
                                                <p class="mt-1 text-xs leading-5 text-zinc-500">{{ $issue->explanation_snapshot }}</p>
                                                <p class="mt-1 text-xs leading-5 text-zinc-500">{{ __('Remediation: :guidance', ['guidance' => $issue->remediation_snapshot]) }}</p>
                                            </div>
                                            <flux:badge :color="$issue->resolution ? 'green' : 'zinc'">{{ $issue->resolution ? str($issue->resolution->outcome)->replace('_', ' ')->title() : __('Open') }}</flux:badge>
                                        </div>
                                        @can('resolveIssue', $sample)
                                            @if (! $issue->resolution)
                                                <flux:button class="mt-3" size="sm" variant="ghost" wire:click="$set('resolutionIssueId', '{{ $issue->id }}')">{{ __('Resolve issue') }}</flux:button>
                                            @endif
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="border-y border-white/8 px-6 py-14 text-center"><p class="text-sm text-zinc-500">{{ __('No synthetic invoice samples have been recorded.') }}</p></div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-6">
            @can('create', \App\Models\InvoiceReadinessSample::class)
                <form wire:submit="createSample" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Record sample') }}</h2>
                    <flux:select wire:model="clientId" :label="__('Client')" required><flux:select.option value="">{{ __('Select client') }}</flux:select.option>@foreach ($this->clients as $client)<flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>@endforeach</flux:select>
                    <flux:input wire:model="sampleReference" :label="__('Synthetic sample reference')" required />
                    <flux:textarea wire:model="sampleSourceReference" :label="__('Manual source reference')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Retain sample identity') }}</flux:button>
                </form>

                <form wire:submit="addField" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Add supplied field') }}</h2>
                    <flux:select wire:model="sampleId" :label="__('Sample')" required><flux:select.option value="">{{ __('Select sample') }}</flux:select.option>@foreach ($this->samples as $sample)<flux:select.option :value="$sample->id">{{ $sample->sample_reference }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="fieldKey" :label="__('Field')" required>@foreach ($this->fieldKeys() as $key)<flux:select.option :value="$key->value">{{ $key->label() }}</flux:select.option>@endforeach</flux:select>
                    <flux:input wire:model="fieldValue" :label="__('Supplied value')" required />
                    <flux:textarea wire:model="fieldSourceReference" :label="__('Manual source reference')" required />
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Retain supplied field') }}</flux:button>
                </form>

                <form wire:submit="recordIssue" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Record invoice issue') }}</h2>
                    <flux:select wire:model="issueSampleId" :label="__('Sample')" required><flux:select.option value="">{{ __('Select sample') }}</flux:select.option>@foreach ($this->samples as $sample)<flux:select.option :value="$sample->id">{{ $sample->sample_reference }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model="issueFieldId" :label="__('Field, if applicable')"><flux:select.option value="">{{ __('Sample-level issue') }}</flux:select.option>@foreach ($this->samples as $sample)@foreach ($sample->fields as $field)<flux:select.option :value="$field->id">{{ $sample->sample_reference }} / {{ $field->field_key->label() }}</flux:select.option>@endforeach @endforeach</flux:select>
                    <flux:select wire:model="issueRuleId" :label="__('Published invoice rule')" required><flux:select.option value="">{{ __('Select rule') }}</flux:select.option>@foreach ($this->publishedInvoiceRules as $rule)<flux:select.option :value="$rule->id">{{ $rule->definition->name }} / v{{ $rule->version }}</flux:select.option>@endforeach</flux:select>
                    <flux:textarea wire:model="issueEvidence" :label="__('Manual issue evidence')" required />
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Retain explainable issue') }}</flux:button>
                </form>
            @endcan

            @if ($resolutionIssueId !== '')
                <form wire:submit="resolveIssue" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                    <h2 class="text-lg font-semibold text-zinc-100">{{ __('Resolve invoice issue') }}</h2>
                    <flux:select wire:model="resolutionOutcome" :label="__('Outcome')" required><flux:select.option value="">{{ __('Select outcome') }}</flux:select.option><flux:select.option value="resolved">{{ __('Resolved') }}</flux:select.option><flux:select.option value="not_applicable">{{ __('Not applicable') }}</flux:select.option></flux:select>
                    <flux:textarea wire:model="resolutionReason" :label="__('Resolution reason')" required />
                    <flux:button type="submit" variant="filled" class="w-full">{{ __('Record issue decision') }}</flux:button>
                </form>
            @endif
        </aside>
    </div>
</div>
