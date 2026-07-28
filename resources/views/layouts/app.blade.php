<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="bg-zinc-950 px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
