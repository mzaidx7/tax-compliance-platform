<div class="mx-auto w-full max-w-6xl">
    <header class="border-b border-white/8 pb-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
        <p class="mb-3 text-sm font-medium text-amber-300">{{ __('Review before saving') }}</p>
        <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Create deadlines from rules') }}</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">
            {{ __('Check the client, period, rule and due date before creating a deadline. Nothing is saved until you review and confirm the preview.') }}
                </p>
            </div>
            <flux:badge color="amber" icon="eye">{{ __('Preview required') }}</flux:badge>
        </div>
    </header>

    <div class="mt-9 grid gap-8 lg:grid-cols-[23rem_minmax(0,1fr)]">
        <section aria-labelledby="generation-input-heading" class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
        <h2 id="generation-input-heading" class="text-lg font-semibold text-zinc-100">{{ __('Deadline details') }}</h2>
        <p class="mt-2 text-sm leading-6 text-zinc-500">{{ __('Select the client, service, period and approved rule. Enter the due date exactly as reviewed by your team.') }}</p>

            <form wire:submit="preview" class="mt-5 space-y-4">
                <flux:select wire:model="clientId" :label="__('Client')" required>
                    <flux:select.option value="">{{ __('Select client') }}</flux:select.option>
                    @foreach ($this->clients as $client)
                        <flux:select.option :value="$client->id">{{ $client->internal_code }} / {{ $client->legal_name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="serviceEnrollmentId" :label="__('Service enrollment')" required>
                    <flux:select.option value="">{{ __('Select service') }}</flux:select.option>
                    @foreach ($this->serviceEnrollments as $service)
                        <flux:select.option :value="$service->id">{{ $service->client->internal_code }} / {{ $service->service->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="taxPeriodId" :label="__('Actual tax period')">
                    <flux:select.option value="">{{ __('No tax period') }}</flux:select.option>
                    @foreach ($this->taxPeriods as $period)
                        <flux:select.option :value="$period->id">{{ $period->registration->client->internal_code }} / {{ $period->label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="ruleVersionId" :label="__('Published rule version')" required>
                    <flux:select.option value="">{{ __('Select rule') }}</flux:select.option>
                    @foreach ($this->publishedRules as $rule)
                        <flux:select.option :value="$rule->id">{{ $rule->template->name }} v{{ $rule->version }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="applicabilityDate" type="date" :label="__('Applicability date')" required />
                <flux:input wire:model="periodLabel" :label="__('Period label')" maxlength="100" required />
                <flux:input wire:model="statutoryDueDate" type="date" :label="__('Manually supplied statutory due date')" required />
                <flux:input wire:model="internalTargetDate" type="date" :label="__('Internal target date')" />
                <flux:button type="submit" variant="primary" class="w-full">{{ __('Create preview') }}</flux:button>
            </form>
        </section>

        <section aria-labelledby="generation-preview-heading">
            <h2 id="generation-preview-heading" class="text-lg font-semibold text-zinc-100">{{ __('Immutable preview') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Committing the same deterministic inputs always resolves to the same obligation.') }}</p>

            @if ($this->previewRun)
                <div class="mt-5 rounded-2xl bg-zinc-900/50 p-6 ring-1 ring-white/8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-zinc-100">{{ $this->previewRun->ruleVersion->template->name }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $this->previewRun->client->legal_name }} / {{ $this->previewRun->serviceEnrollment->service->label() }}</p>
                        </div>
                        <flux:badge color="amber">{{ $this->previewRun->status->label() }}</flux:badge>
                    </div>

                    <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-zinc-500">{{ __('Statutory due date') }}</dt>
                            <dd class="mt-1 text-lg font-semibold text-white">{{ $this->previewRun->statutory_due_date->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">{{ __('Internal target') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-300">{{ $this->previewRun->internal_target_date?->format('d M Y') ?? __('Not set') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-zinc-500">{{ __('Calculation explanation') }}</dt>
                            <dd class="mt-1 text-sm leading-6 text-zinc-300">{{ $this->previewRun->calculation_explanation }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-zinc-500">{{ __('Deterministic key') }}</dt>
                            <dd class="mt-1 break-all font-mono text-xs text-zinc-500">{{ $this->previewRun->deterministic_key }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6 border-t border-white/8 pt-5">
                        @if ($committedObligationId)
                            <flux:callout variant="success" icon="check-circle" heading="{{ __('Obligation committed') }}">
                                {{ __('Obligation :id now retains this exact snapshot.', ['id' => $committedObligationId]) }}
                            </flux:callout>
                        @else
                            <flux:button wire:click="commit" variant="filled" class="w-full">{{ __('Commit this exact preview') }}</flux:button>
                        @endif
                    </div>
                </div>
            @else
                <div class="mt-5 border-y border-white/8 px-6 py-16 text-center">
                    <p class="text-sm font-medium text-zinc-200">{{ __('No preview created') }}</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">{{ __('Choose explicit records and dates to calculate and retain a preview snapshot.') }}</p>
                </div>
            @endif
        </section>
    </div>

    <section aria-labelledby="rule-change-heading" class="mt-12 border-t border-white/8 pt-10">
        <div class="max-w-3xl">
            <p class="text-sm font-medium text-amber-300">{{ __('Changed-rule control') }}</p>
            <h2 id="rule-change-heading" class="mt-2 text-2xl font-semibold tracking-tight text-white">{{ __('Compare before superseding') }}</h2>
            <p class="mt-2 text-sm leading-6 text-zinc-500">{{ __('A proposal preserves the issued obligation and records the old and proposed dates. Approval is a separate action that issues a deterministic replacement and retains the original as superseded.') }}</p>
        </div>

        <div class="mt-6 grid gap-8 lg:grid-cols-[23rem_minmax(0,1fr)]">
            <form wire:submit="proposeRuleChange" class="space-y-4 rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <flux:select wire:model="proposalOriginalObligationId" :label="__('Issued governed obligation')" required>
                    <flux:select.option value="">{{ __('Select issued obligation') }}</flux:select.option>
                    @foreach ($this->issuedGovernedObligations as $issued)
                        <flux:select.option :value="$issued->id">
                            {{ $issued->client->internal_code }} / {{ $issued->ruleVersion->template->name }} v{{ $issued->ruleVersion->version }} / {{ $issued->statutory_due_date->format('d M Y') }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="proposalRuleVersionId" :label="__('Later published rule')" required>
                    <flux:select.option value="">{{ __('Select later rule') }}</flux:select.option>
                    @foreach ($this->publishedRules as $rule)
                        <flux:select.option :value="$rule->id">{{ $rule->template->name }} v{{ $rule->version }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="proposalStatutoryDueDate" type="date" :label="__('Proposed statutory due date')" required />
                <flux:input wire:model="proposalInternalTargetDate" type="date" :label="__('Proposed internal target')" />
                <flux:textarea wire:model="proposalReason" :label="__('Proposal reason')" rows="4" maxlength="500" required />
                <flux:button type="submit" variant="primary" class="w-full">{{ __('Record comparison proposal') }}</flux:button>
            </form>

            <div>
                @if ($this->activeProposal)
                    <div class="rounded-2xl bg-zinc-900/50 p-6 ring-1 ring-white/8">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-zinc-100">{{ $this->activeProposal->originalObligation->client->legal_name }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $this->activeProposal->proposedRuleVersion->template->name }} v{{ $this->activeProposal->proposedRuleVersion->version }}</p>
                            </div>
                            <flux:badge :color="$this->activeProposal->decision ? 'green' : 'amber'">
                                {{ $this->activeProposal->decision ? __('Approved') : __('Awaiting approval') }}
                            </flux:badge>
                        </div>
                        <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-zinc-500">{{ __('Issued date') }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-zinc-300">{{ $this->activeProposal->original_statutory_due_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500">{{ __('Proposed date') }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-amber-300">{{ $this->activeProposal->proposed_statutory_due_date->format('d M Y') }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-zinc-500">{{ __('Calculation explanation') }}</dt>
                                <dd class="mt-1 text-sm leading-6 text-zinc-300">{{ $this->activeProposal->previewRun->calculation_explanation }}</dd>
                            </div>
                        </dl>
                        @if (! $this->activeProposal->decision)
                            <form wire:submit="approveRuleChange" class="mt-6 space-y-4 border-t border-white/8 pt-5">
                                <flux:textarea wire:model="approvalReason" :label="__('Approval reason')" rows="3" maxlength="500" required />
                                <flux:button type="submit" variant="filled" class="w-full">{{ __('Approve and issue replacement') }}</flux:button>
                            </form>
                        @else
                            <flux:callout class="mt-6" variant="success" icon="check-circle" heading="{{ __('Replacement issued') }}">
                                {{ __('Replacement obligation :id is retained separately.', ['id' => $this->activeProposal->decision->replacement_obligation_id]) }}
                            </flux:callout>
                        @endif
                    </div>
                @else
                    <div class="border-y border-white/8 px-6 py-16 text-center">
                        <p class="text-sm font-medium text-zinc-200">{{ __('No changed-rule proposal selected') }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-500">{{ __('Record a comparison before any issued obligation can be superseded.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
