<div>
    <main class="mx-auto flex min-h-[calc(100vh-5rem)] w-full max-w-7xl items-center px-6 py-12 lg:px-10">
        <div class="grid w-full gap-12 lg:grid-cols-[minmax(0,1.05fr)_minmax(22rem,0.75fr)] lg:items-center">
            <section aria-labelledby="readiness-heading">
                <div class="mb-8 inline-flex items-center gap-2 rounded-full bg-amber-300/10 px-3 py-1.5 text-sm font-medium text-amber-300 ring-1 ring-amber-300/20">
                    <span class="size-1.5 rounded-full bg-amber-300"></span>
                    {{ __('Planned for a future release') }}
                </div>
                <h1 id="readiness-heading" class="max-w-3xl text-balance text-5xl font-semibold tracking-[-0.04em] text-white sm:text-6xl">
                    {{ __('E-invoicing readiness is coming soon.') }}
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-400">
                    {{ __('The current release focuses on day-to-day tax and compliance work. Tools for reviewing and improving e-invoicing data will be released separately after further testing.') }}
                </p>

                <div class="mt-10 flex flex-wrap gap-3">
                    <flux:button variant="primary" :href="route('dashboard')" wire:navigate icon="arrow-left">
                        {{ __('Return to compliance dashboard') }}
                    </flux:button>
                    <flux:button variant="ghost" :href="route('clients.index')" wire:navigate>
                        {{ __('Open client register') }}
                    </flux:button>
                </div>
            </section>

            <aside aria-label="{{ __('Future readiness capabilities') }}" class="border-y border-white/10 py-2">
                @foreach ([
                    [__('Master-data assessment'), __('Review customer and supplier records against approved, explainable rules.')],
                    [__('Controlled corrections'), __('Propose, review and retain field-level corrections with clear provenance.')],
                    [__('Readiness reporting'), __('Track unresolved issues and progress without claiming guaranteed compliance.')],
                ] as [$title, $description])
                    <div class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-4 border-b border-white/8 py-6 last:border-b-0">
                        <div class="grid size-10 place-items-center rounded-xl bg-zinc-900 text-amber-300 ring-1 ring-white/10">
                            <flux:icon.lock-closed class="size-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-100">{{ $title }}</h2>
                            <p class="mt-2 text-sm leading-6 text-zinc-500">{{ $description }}</p>
                        </div>
                    </div>
                @endforeach
            </aside>
        </div>
    </main>
</div>
