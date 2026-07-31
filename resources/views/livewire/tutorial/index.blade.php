@php
    $currentStep = $steps[$step - 1];
    $progress = round(($step / \App\Livewire\Tutorial\Index::STEP_COUNT) * 100);
@endphp

<div class="tbt-page">
    <header class="tbt-page-header">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="tbt-page-kicker">{{ __('Help centre') }}</p>
                <h1 class="tbt-page-title">{{ __('Learn the compliance workflow') }}</h1>
                <p class="tbt-page-copy">
                    {{ __('A practical four-minute guide to managing clients, documents, VAT, Corporate Tax and team work across the firm.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($completed)
                    <flux:badge color="green" icon="check-circle">{{ __('Tutorial completed') }}</flux:badge>
                    <flux:button variant="ghost" icon="arrow-path" wire:click="restartTutorial">
                        {{ __('Start again') }}
                    </flux:button>
                @else
                    <flux:badge color="amber">{{ __('Step :step of :total', ['step' => $step, 'total' => \App\Livewire\Tutorial\Index::STEP_COUNT]) }}</flux:badge>
                @endif
            </div>
        </div>
    </header>

    <div class="tbt-tutorial-progress mt-5" aria-label="{{ __('Tutorial progress') }}">
        <div class="tbt-tutorial-progress__track" aria-hidden="true">
            <span style="--tutorial-progress: {{ $progress / 100 }}"></span>
        </div>
        <p>{{ __(':percent% complete', ['percent' => $progress]) }}</p>
    </div>

    <div class="tbt-tutorial-layout mt-5">
        <nav class="tbt-tutorial-nav" aria-label="{{ __('Tutorial steps') }}">
            <p class="tbt-tutorial-nav__heading">{{ __('Guide contents') }}</p>
            <ol>
                @foreach ($steps as $index => $tutorialStep)
                    @php $stepNumber = $index + 1; @endphp
                    <li>
                        <button
                            type="button"
                            wire:click="goToStep({{ $stepNumber }})"
                            @class(['is-current' => $step === $stepNumber, 'is-past' => $step > $stepNumber])
                            @if ($step === $stepNumber) aria-current="step" @endif
                        >
                            <span aria-hidden="true">
                                @if ($step > $stepNumber)
                                    <flux:icon.check class="size-3.5" />
                                @else
                                    {{ $stepNumber }}
                                @endif
                            </span>
                            <strong>{{ $tutorialStep['short_title'] }}</strong>
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        <section class="tbt-tutorial-stage" aria-labelledby="tutorial-step-heading">
            <div class="tbt-tutorial-stage__header">
                <div class="tbt-tutorial-stage__icon" aria-hidden="true">
                    <flux:icon :name="$currentStep['icon']" class="size-6" />
                </div>
                <div>
                    <p>{{ __('Step :step', ['step' => $step]) }}</p>
                    <h2 id="tutorial-step-heading">{{ $currentStep['title'] }}</h2>
                </div>
            </div>

            <p class="tbt-tutorial-stage__lead">{{ $currentStep['description'] }}</p>

            <div class="tbt-tutorial-outcome">
                <flux:icon.light-bulb class="size-5" aria-hidden="true" />
                <div>
                    <strong>{{ __('What you will know') }}</strong>
                    <p>{{ $currentStep['outcome'] }}</p>
                </div>
            </div>

            <ul class="tbt-tutorial-points">
                @foreach ($currentStep['points'] as $point)
                    <li>
                        <flux:icon.check-circle class="size-5" aria-hidden="true" />
                        <span>{{ $point }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="tbt-tutorial-stage__actions">
                <div>
                    @if ($currentStep['action_available'])
                        <flux:button :href="$currentStep['action_url']" variant="ghost" icon="arrow-top-right-on-square" wire:navigate>
                            {{ $currentStep['action_label'] }}
                        </flux:button>
                    @else
                        <p class="text-sm text-[var(--tbt-muted)]">{{ __('This page is not available for your current role.') }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" icon="arrow-left" wire:click="previousStep" :disabled="$step === 1">
                        {{ __('Previous') }}
                    </flux:button>

                    @if ($step < \App\Livewire\Tutorial\Index::STEP_COUNT)
                        <flux:button variant="filled" icon:trailing="arrow-right" wire:click="nextStep">
                            {{ __('Next step') }}
                        </flux:button>
                    @else
                        <flux:button variant="filled" icon="check" wire:click="completeTutorial">
                            {{ $completed ? __('Completed') : __('Finish tutorial') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </section>
    </div>

    <section class="mt-7" aria-labelledby="tutorial-reference-heading">
        <div class="tbt-section-heading">
            <div>
                <h2 id="tutorial-reference-heading">{{ __('Quick reference') }}</h2>
                <p>{{ __('Plain-language explanations for terms used throughout the platform.') }}</p>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            <details class="tbt-accordion">
                <summary>{{ __('Which date should I follow?') }}</summary>
                <div class="p-5 text-sm leading-6 text-[var(--tbt-muted)]">
                    <p><strong class="text-[var(--tbt-text)]">{{ __('Filing due date') }}</strong> {{ __('is the recorded submission deadline.') }}</p>
                    <p class="mt-3"><strong class="text-[var(--tbt-text)]">{{ __('Team target date') }}</strong> {{ __('is your firm’s earlier internal completion target.') }}</p>
                </div>
            </details>

            <details class="tbt-accordion">
                <summary>{{ __('What does “date checked” mean?') }}</summary>
                <div class="p-5 text-sm leading-6 text-[var(--tbt-muted)]">
                    {{ __('It records when a team member last confirmed the source date. It does not mean the return was filed or accepted by the authority.') }}
                </div>
            </details>

            <details class="tbt-accordion">
                <summary>{{ __('Who can see and change records?') }}</summary>
                <div class="p-5 text-sm leading-6 text-[var(--tbt-muted)]">
                    {{ __('Your role and firm membership control the pages and actions available to you. Ask a firm administrator if an expected page is missing.') }}
                </div>
            </details>

            <details class="tbt-accordion">
                <summary>{{ __('Does the platform store FTA passwords?') }}</summary>
                <div class="p-5 text-sm leading-6 text-[var(--tbt-muted)]">
                    {{ __('No. FTA, UAE Pass and client email passwords must never be entered or stored in this platform.') }}
                </div>
            </details>
        </div>
    </section>
</div>
