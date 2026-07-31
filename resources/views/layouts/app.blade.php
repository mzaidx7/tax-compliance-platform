<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="main-content" class="tbt-main px-4 py-5 sm:px-6 sm:py-7 lg:px-8 lg:py-8 xl:px-10">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
