<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => __('Choose a firm')])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        <main class="relative isolate min-h-screen overflow-hidden px-5 py-10 sm:px-8 lg:px-12">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(circle_at_top,rgba(212,166,74,.15),transparent_65%)]"></div>

            <div class="relative mx-auto flex min-h-[calc(100vh-5rem)] max-w-5xl flex-col">
                <header class="flex items-center justify-between border-b border-white/8 pb-5">
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
                        <p class="mb-3 text-sm font-medium text-amber-300">{{ __('Firm context') }}</p>
                        <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                            {{ __('Choose where you are working') }}
                        </h1>
                        <p class="mt-5 max-w-xl text-base leading-7 text-zinc-400">
                            {{ __('Your access and records are isolated by firm. Select a workspace to continue securely.') }}
                        </p>
                    </div>

                    <div class="mt-10 divide-y divide-white/8 overflow-hidden rounded-2xl bg-zinc-900/80 ring-1 ring-white/10">
                        @foreach ($memberships as $membership)
                            <form method="POST" action="{{ route('firms.switch', $membership->firm_id) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="group flex min-h-24 w-full items-center gap-4 px-5 py-4 text-left outline-none transition hover:bg-white/4 focus-visible:bg-white/4 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400 sm:px-7"
                                >
                                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-amber-400 text-lg font-black text-black">
                                        {{ mb_strtoupper(mb_substr($membership->firm->name, 0, 1)) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-base font-semibold text-zinc-100">
                                            {{ $membership->firm->name }}
                                        </span>
                                        <span class="mt-1 block text-sm text-zinc-400">
                                            {{ $membership->role->label() }}
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-2 text-sm font-medium text-zinc-500 transition group-hover:text-amber-300">
                                        <span class="hidden sm:inline">{{ __('Open workspace') }}</span>
                                        <flux:icon.arrow-right class="size-4" />
                                    </span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </section>

                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-white/8 pt-5 text-xs text-zinc-500">
                    <span>{{ __('TBT Compliance Platform') }}</span>
                    <span>{{ __('Access is verified before every workspace is opened.') }}</span>
                </footer>
            </div>
        </main>

        @fluxScripts
    </body>
</html>
