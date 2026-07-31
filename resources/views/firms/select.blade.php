<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Choose a firm')])
    </head>
    <body class="min-h-screen bg-[var(--tbt-canvas)] text-[var(--tbt-text)] antialiased">
        <main id="main-content" class="relative isolate min-h-screen overflow-hidden px-5 py-8 sm:px-8 sm:py-10 lg:px-12">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-[30rem] bg-[radial-gradient(circle_at_top,rgba(212,166,74,.17),transparent_63%)]"></div>

            <div class="relative mx-auto flex min-h-[calc(100vh-5rem)] max-w-5xl flex-col">
                <header class="flex items-center justify-between border-b border-[var(--tbt-border)] pb-5">
                    <x-app-logo href="{{ route('home') }}" />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle">
                            {{ __('Log out') }}
                        </flux:button>
                    </form>
                </header>

                <section class="my-auto py-12">
                    <div class="max-w-2xl">
                        <p class="tbt-page-kicker mb-3">{{ __('Choose firm') }}</p>
                        <h1 class="text-balance text-4xl font-semibold tracking-[-0.04em] text-[var(--tbt-text-strong)] sm:text-5xl">
                            {{ __('Choose where you are working') }}
                        </h1>
                        <p class="mt-5 max-w-xl text-base leading-7 text-[var(--tbt-text-muted)]">
                            {{ __('Your access and records are isolated by firm. Select a workspace to continue securely.') }}
                        </p>
                    </div>

                    <div class="tbt-panel mt-10 divide-y divide-[var(--tbt-border)] overflow-hidden">
                        @foreach ($memberships as $membership)
                            <form method="POST" action="{{ route('firms.switch', $membership->firm_id) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="group flex min-h-24 w-full items-center gap-4 px-5 py-4 text-left outline-none transition hover:bg-[var(--tbt-row-hover)] focus-visible:bg-[var(--tbt-row-hover)] focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400 sm:px-7"
                                >
                                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-amber-400 text-lg font-black text-black">
                                        {{ mb_strtoupper(mb_substr($membership->firm->name, 0, 1)) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-base font-semibold text-[var(--tbt-text-strong)]">
                                            {{ $membership->firm->name }}
                                        </span>
                                        <span class="mt-1 block text-sm text-[var(--tbt-text-muted)]">
                                            {{ $membership->role->label() }}
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-2 text-sm font-medium text-[var(--tbt-text-muted)] transition group-hover:text-amber-300">
                                        <span class="hidden sm:inline">{{ __('Open workspace') }}</span>
                                        <flux:icon.arrow-right class="size-4" />
                                    </span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </section>

                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--tbt-border)] pt-5 text-xs text-[var(--tbt-text-muted)]">
                    <span>{{ __('TBT Compliance Platform') }}</span>
                    <span>{{ __('Access is verified before every workspace is opened.') }}</span>
                </footer>
            </div>
        </main>

        @livewireScriptConfig
        @fluxScripts
    </body>
</html>
