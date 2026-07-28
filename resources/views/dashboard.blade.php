{{--
THESIS: Firm context is the dashboard, not a decorative metric grid.
OWN-WORLD: Matte ink surfaces, silver type, and restrained gold current-state cues.
STORY: The user confirms the workspace, sees the security foundation, and moves to the next authorised task.
FIRST VIEWPORT: Firm identity and a compact operational register lead; the primary action sits beside the register.
FORM: Context workbench, assigned structure six, seed a57a18dc.
--}}
<x-layouts::app :title="__('Dashboard')">
    @php
        $firmContext = app(\App\Tenancy\FirmContext::class);
        $firm = $firmContext->firm();
        $membership = $firmContext->membership();
    @endphp

    <div class="mx-auto w-full max-w-7xl">
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" class="mb-7" :heading="session('status')" />
        @endif

        <header class="border-b border-white/8 pb-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="mb-3 text-sm font-medium text-amber-300">{{ $firm->name }}</p>
                    <h1 class="max-w-3xl text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                        {{ __('Your secure workspace is ready.') }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-zinc-400">
                        {{ __('Firm context, member access and audit history are active. Operational modules will be added here as each verified build packet is completed.') }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <flux:badge color="amber" icon="building-office-2">{{ $membership?->role->label() }}</flux:badge>
                    @can('viewAny', \App\Models\FirmMembership::class)
                        <flux:button :href="route('members.index')" variant="primary" icon="users" wire:navigate>
                            {{ __('Manage access') }}
                        </flux:button>
                    @endcan
                </div>
            </div>
        </header>

        <div class="mt-9 grid gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section aria-labelledby="foundation-heading">
                <div class="mb-5">
                    <h2 id="foundation-heading" class="text-lg font-semibold text-zinc-100">{{ __('Workspace foundation') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Security controls currently active for this firm.') }}</p>
                </div>

                <div class="divide-y divide-white/8 border-y border-white/8">
                    <div class="grid gap-2 py-5 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-center">
                        <span class="text-sm font-medium text-zinc-200">{{ __('Firm isolation') }}</span>
                        <span class="text-sm text-zinc-500">{{ __('Requests and records resolve through verified membership.') }}</span>
                        <flux:badge color="green">{{ __('Active') }}</flux:badge>
                    </div>
                    <div class="grid gap-2 py-5 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-center">
                        <span class="text-sm font-medium text-zinc-200">{{ __('Access control') }}</span>
                        <span class="text-sm text-zinc-500">{{ __('Named permissions protect firm-level actions.') }}</span>
                        <flux:badge color="green">{{ __('Active') }}</flux:badge>
                    </div>
                    <div class="grid gap-2 py-5 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-center">
                        <span class="text-sm font-medium text-zinc-200">{{ __('Audit history') }}</span>
                        <span class="text-sm text-zinc-500">{{ __('Significant actions are recorded with sensitive values redacted.') }}</span>
                        <flux:badge color="green">{{ __('Active') }}</flux:badge>
                    </div>
                </div>
            </section>

            <aside class="rounded-2xl bg-zinc-900/70 p-6 ring-1 ring-white/8">
                <p class="text-sm font-semibold text-zinc-100">{{ __('Next safe action') }}</p>
                <p class="mt-3 text-sm leading-6 text-zinc-500">
                    {{ __('Confirm the people who should access this firm before client and compliance records are introduced.') }}
                </p>
                @can('viewAny', \App\Models\FirmMembership::class)
                    <flux:button :href="route('members.index')" variant="filled" class="mt-6 w-full" icon="user-plus" wire:navigate>
                        {{ __('Review firm members') }}
                    </flux:button>
                @else
                    <p class="mt-6 text-xs leading-5 text-zinc-500">
                        {{ __('A firm administrator manages membership and invitations.') }}
                    </p>
                @endcan
            </aside>
        </div>
    </div>
</x-layouts::app>
