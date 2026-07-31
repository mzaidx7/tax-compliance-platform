<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <div class="relative isolate min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(circle_at_22%_0%,rgba(251,191,36,0.11),transparent_52%)]"></div>

            <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-10">
                <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-4 focus-visible:ring-offset-zinc-950">
                    <span class="grid size-9 place-items-center rounded-xl bg-amber-300 text-black">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-white">{{ __('TBT Compliance') }}</span>
                        <span class="block text-xs text-zinc-500">{{ __('Client deadline management') }}</span>
                    </span>
                </a>

                @auth
                    <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                        {{ __('Open workspace') }}
                    </flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" wire:navigate icon-trailing="arrow-right">
                        {{ __('Team sign in') }}
                    </flux:button>
                @endauth
            </header>

            <main>
                <section class="mx-auto grid w-full max-w-7xl gap-14 px-6 pb-20 pt-16 lg:grid-cols-[minmax(0,1.05fr)_minmax(26rem,0.8fr)] lg:items-center lg:px-10 lg:pb-28 lg:pt-24">
                    <div>
                        <div class="mb-7 inline-flex items-center gap-2 rounded-full bg-white/[0.035] px-3 py-1.5 text-sm text-zinc-300 ring-1 ring-white/10">
                            <span class="size-1.5 rounded-full bg-green-400"></span>
                            {{ __('TBT Compliance Platform') }}
                        </div>
                        <h1 class="max-w-4xl text-balance text-5xl font-semibold tracking-[-0.04em] text-white sm:text-6xl lg:text-7xl">
                            {{ __('Every client deadline, document expiry and assigned task in one secure workspace.') }}
                        </h1>
                        <p class="mt-7 max-w-2xl text-lg leading-8 text-zinc-400">
                            {{ __('Manage clients, deadlines, documents, assignments, reviews and reports in one secure workspace. Important changes remain available in the activity history.') }}
                        </p>

                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            @auth
                                <flux:button :href="route('dashboard')" variant="primary" wire:navigate icon-trailing="arrow-right" class="min-h-11 px-5">
                                    {{ __('Continue to dashboard') }}
                                </flux:button>
                            @else
                                <flux:button :href="route('login')" variant="primary" wire:navigate icon-trailing="arrow-right" class="min-h-11 px-5">
                                    {{ __('Sign in to your workspace') }}
                                </flux:button>
                            @endauth
                            <span class="text-sm text-zinc-500">{{ __('Invitation-only access') }}</span>
                        </div>
                    </div>

                    <aside aria-label="{{ __('Available features') }}" class="relative">
                        <div class="absolute -inset-5 -z-10 rounded-[2rem] bg-amber-300/[0.025] blur-2xl"></div>
                        <div class="overflow-hidden rounded-2xl bg-zinc-900/80 ring-1 ring-white/10">
                            <div class="flex items-center justify-between border-b border-white/8 px-6 py-5">
                                <div>
                                    <p class="text-sm font-semibold text-zinc-100">{{ __('What you can manage') }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ __('Clear records for every client') }}</p>
                                </div>
                                <span class="rounded-full bg-green-400/10 px-2.5 py-1 text-xs font-medium text-green-300">{{ __('Release 1') }}</span>
                            </div>

                            <div class="divide-y divide-white/8 px-6">
                                @foreach ([
                                    [__('Compliance calendar'), __('VAT, Corporate Tax and document dates for all clients'), 'calendar-days'],
                                    [__('Client tasks'), __('Assignments, checklists, reviews and follow-ups'), 'queue-list'],
                                    [__('Records and reports'), __('Document details, change history and downloadable reports'), 'shield-check'],
                                ] as [$title, $description, $icon])
                                    <div class="grid grid-cols-[2.75rem_minmax(0,1fr)] gap-4 py-6">
                                        <span class="grid size-11 place-items-center rounded-xl bg-zinc-950 text-amber-300 ring-1 ring-white/8">
                                            <flux:icon :name="$icon" class="size-5" />
                                        </span>
                                        <div>
                                            <h2 class="text-sm font-semibold text-zinc-100">{{ $title }}</h2>
                                            <p class="mt-1.5 text-sm leading-6 text-zinc-500">{{ $description }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    </aside>
                </section>

                <section class="border-y border-white/8 bg-zinc-900/30">
                    <div class="mx-auto grid w-full max-w-7xl gap-px px-6 sm:grid-cols-3 lg:px-10">
                        @foreach ([
                            [__('Private firm workspace'), __('Users only see clients and records for firms they can access.')],
                            [__('Dates reviewed by people'), __('Important tax dates keep their source and the date they were checked.')],
                            [__('Complete change history'), __('Important changes retain who changed what and when.')],
                        ] as [$title, $description])
                            <div class="py-9 sm:px-7 sm:first:pl-0 sm:last:pr-0">
                                <h2 class="text-sm font-semibold text-zinc-200">{{ $title }}</h2>
                                <p class="mt-2 max-w-sm text-sm leading-6 text-zinc-500">{{ $description }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </main>

            <footer class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-6 py-8 text-xs text-zinc-600 sm:flex-row sm:items-center sm:justify-between lg:px-10">
                <p>{{ __('TBT Compliance Platform') }}</p>
                <p>{{ __('No authority affiliation, automated filing or guaranteed compliance is claimed.') }}</p>
            </footer>
        </div>

        @livewireScriptConfig
        @fluxScripts
    </body>
</html>
