<div class="mx-auto w-full max-w-6xl">
    <header class="border-b border-white/8 pb-8">
        <p class="mb-3 text-sm font-medium text-amber-300">{{ app(\App\Tenancy\FirmContext::class)->firm()->name }}</p>
        <h1 class="text-balance text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">{{ __('Notifications') }}</h1>
        <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-400">{{ __('Review reminders and updates about your firm’s compliance work. Delivery status is shown without exposing private message content.') }}</p>
    </header>

    @if ($this->canGenerateSummary())
        <section class="mt-8 border-y border-white/8 py-5" aria-labelledby="manager-summary-heading">
            <div class="grid gap-4 sm:grid-cols-[minmax(14rem,1fr)_auto] sm:items-end">
                <div>
                    <h2 id="manager-summary-heading" class="text-lg font-semibold text-zinc-100">{{ __('Send a manager summary') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Send a daily summary of upcoming deadlines, overdue work, recorded risks and payment follow-ups to an active manager.') }}</p>
                    <div class="mt-4 max-w-xl">
                        <flux:select wire:model="managerMembershipId" :label="__('Recipient manager')" required>
                            <flux:select.option value="">{{ __('Select active manager') }}</flux:select.option>
                            @foreach ($this->managers as $manager)
                                <flux:select.option :value="$manager->id">{{ $manager->user->name }} / {{ $manager->role->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
                <flux:button variant="filled" wire:click="generateManagerSummary" :disabled="$managerMembershipId === ''">{{ __('Send summary') }}</flux:button>
            </div>
        </section>
    @endif

    <section class="mt-9" aria-labelledby="notice-history-heading">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 id="notice-history-heading" class="text-lg font-semibold text-zinc-100">{{ __('Your notices') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('See whether each notification is waiting, delivered or unsuccessful, and whether it has been read.') }}</p>
            </div>
            <span class="text-xs text-zinc-500">{{ trans_choice('{0} No notices|{1} 1 notice|[2,*] :count notices', $this->requests->total(), ['count' => $this->requests->total()]) }}</span>
        </div>

        <div class="divide-y divide-white/8 border-y border-white/8">
            @forelse ($this->requests as $request)
                @php($latestAttempt = $request->attempts->first())
                <article class="grid gap-4 py-5 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-center">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-zinc-100">{{ $this->templateLabel($request->template_key) }}</h3>
                            @if (! $request->readReceipt)<span class="text-xs font-medium text-amber-300">{{ __('Unread') }}</span>@endif
                        </div>
                        <p class="mt-2 text-sm text-zinc-500">{{ __('Requested :date via :channel', ['date' => $request->scheduled_at->format('d M Y, H:i'), 'channel' => $request->channel->value]) }}</p>
                    </div>
                    <div>
                        <flux:badge :color="$request->status->value === 'delivered' ? 'green' : ($request->status->value === 'failed' ? 'red' : 'amber')">
                            {{ str($request->status->value)->replace('_', ' ')->title() }}
                        </flux:badge>
                        @if ($latestAttempt)
                            <p class="mt-2 text-xs text-zinc-500">{{ __('Attempt :number / :state', ['number' => $latestAttempt->attempt_number, 'state' => str($latestAttempt->status->value)->title()]) }}</p>
                        @endif
                    </div>
                    <div class="md:justify-self-end">
                        @if (! $request->readReceipt)
                            <flux:button size="sm" variant="ghost" wire:click="markRead('{{ $request->id }}')">{{ __('Mark read') }}</flux:button>
                        @else
                            <span class="text-xs text-zinc-500">{{ __('Read :date', ['date' => $request->readReceipt->read_at->format('d M Y, H:i')]) }}</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="py-14 text-center"><p class="text-sm font-medium text-zinc-200">{{ __('No notification evidence yet') }}</p><p class="mt-2 text-sm text-zinc-500">{{ __('Operational notices addressed to you will appear here.') }}</p></div>
            @endforelse
        </div>
        @if ($this->requests->hasPages())<div class="mt-6">{{ $this->requests->links() }}</div>@endif
    </section>
</div>
