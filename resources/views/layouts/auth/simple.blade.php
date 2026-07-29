<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden bg-zinc-50 p-6 md:bg-zinc-50 md:p-10 dark:bg-zinc-950">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(circle_at_top,rgba(212,166,74,.14),transparent_65%)]"></div>
            <div class="relative flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="mb-1 flex size-10 items-center justify-center rounded-xl bg-amber-400">
                        <x-app-logo-icon class="size-6 text-zinc-950" />
                    </span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">{{ config('app.name', 'TBT Compliance Platform') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
