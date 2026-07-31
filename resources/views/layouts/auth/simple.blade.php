<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-[var(--tbt-canvas)] p-5 sm:p-8 md:p-10">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-[32rem] bg-[radial-gradient(circle_at_top,rgba(212,166,74,.18),transparent_62%)]"></div>
            <div class="pointer-events-none absolute inset-y-0 left-0 hidden w-1/3 bg-[linear-gradient(135deg,rgba(212,166,74,.04),transparent_65%)] lg:block"></div>

            <div class="relative w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-6 flex items-center justify-center gap-3 font-medium" wire:navigate>
                    <span class="flex size-11 items-center justify-center rounded-xl bg-amber-400 shadow-[0_10px_30px_rgba(212,166,74,.22)]">
                        <x-app-logo-icon class="size-6 text-zinc-950" />
                    </span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ config('app.name', 'TBT Compliance Platform') }}</span>
                </a>

                <div class="tbt-panel relative overflow-hidden p-6 sm:p-8">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-500 via-amber-300 to-transparent"></div>
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>

                <p class="mt-5 text-center text-xs leading-5 text-[var(--tbt-text-muted)]">
                    {{ __('Secure access for authorised TBT compliance teams.') }}
                </p>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @livewireScriptConfig
        @fluxScripts
    </body>
</html>
