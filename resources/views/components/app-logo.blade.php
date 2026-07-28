@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="TBT Compliance" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-amber-400 text-zinc-950">
            <x-app-logo-icon class="size-5 text-zinc-950" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="TBT Compliance" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-amber-400 text-zinc-950">
            <x-app-logo-icon class="size-5 text-zinc-950" />
        </x-slot>
    </flux:brand>
@endif
