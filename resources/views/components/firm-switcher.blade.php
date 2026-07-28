<flux:dropdown position="bottom" align="start">
    <button
        type="button"
        class="group flex w-full items-center gap-3 rounded-xl bg-zinc-950/80 px-3 py-3 text-left outline-none ring-1 ring-white/8 transition hover:bg-zinc-800 focus-visible:ring-2 focus-visible:ring-amber-400"
        aria-label="{{ __('Switch firm. Current firm: :firm', ['firm' => $currentMembership->firm->name]) }}"
    >
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-amber-400 text-sm font-black text-zinc-950">
            {{ mb_strtoupper(mb_substr($currentMembership->firm->name, 0, 1)) }}
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-zinc-100">
                {{ $currentMembership->firm->name }}
            </span>
            <span class="block truncate text-xs text-zinc-400">
                {{ $currentMembership->role->label() }}
            </span>
        </span>
        <flux:icon.chevrons-up-down class="size-4 text-zinc-500 transition group-hover:text-amber-300" />
    </button>

    <flux:menu class="min-w-72">
        <div class="px-2 py-1.5">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Firm workspace') }}</p>
        </div>

        @foreach ($memberships as $membership)
            <form method="POST" action="{{ route('firms.switch', $membership->firm_id) }}">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="{{ $membership->firm_id === $currentMembership->firm_id ? 'check' : 'building-office-2' }}"
                    class="w-full cursor-pointer"
                >
                    <span class="grid text-left">
                        <span>{{ $membership->firm->name }}</span>
                        <span class="text-xs text-zinc-500">{{ $membership->role->label() }}</span>
                    </span>
                </flux:menu.item>
            </form>
        @endforeach

        <flux:menu.separator />
        <flux:menu.item :href="route('firms.select')" icon="arrows-right-left">
            {{ __('View all firms') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
