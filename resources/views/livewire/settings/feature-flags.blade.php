<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Feature administration') }}</flux:heading>

    <x-settings.layout
        :heading="__('Features')"
        :subheading="__('Enable or disable features for this firm. Every change is recorded in the audit register.')"
    >
        <div class="my-6 w-full space-y-4">
            @foreach ($this->features as $feature)
                <div class="flex items-start justify-between gap-4 rounded-xl bg-zinc-900/60 p-4 ring-1 ring-white/8">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-zinc-100">{{ $feature['label'] }}</p>
                            <flux:badge size="sm" :color="$feature['effective'] ? 'green' : 'zinc'">
                                {{ $feature['effective'] ? __('Enabled') : __('Disabled') }}
                            </flux:badge>
                            @if ($feature['overridden'])
                                <flux:badge size="sm" color="amber">{{ __('Firm override') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Platform default') }}</flux:badge>
                            @endif
                        </div>
                        <p class="mt-1 text-xs leading-5 text-zinc-500">{{ $feature['description'] }}</p>
                    </div>

                    <flux:button size="sm" variant="ghost" wire:click="openOverride('{{ $feature['value'] }}')">
                        {{ __('Change') }}
                    </flux:button>
                </div>
            @endforeach
        </div>
    </x-settings.layout>

    <flux:modal name="feature-flag-override" wire:model="showModal" @close="closeModal" class="max-w-lg">
        <form wire:submit="saveOverride" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Change feature availability') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Record an explicit decision for :feature. A change only shifts availability. Each guarded action still enforces its own permission.', [
                        'feature' => $this->editingLabel,
                    ]) }}
                </flux:text>
            </div>

            <flux:switch wire:model="desiredEnabled" :label="__('Enabled for this firm')" />

            <flux:textarea
                wire:model="reason"
                :label="__('Reason')"
                :description="__('Required. Record why this feature is being enabled or disabled.')"
                rows="3"
                maxlength="500"
                required
            />
            <flux:error name="reason" />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <flux:button type="button" variant="ghost" wire:click="closeModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveOverride">
                    <span wire:loading.remove wire:target="saveOverride">{{ __('Record change') }}</span>
                    <span wire:loading wire:target="saveOverride">{{ __('Recording change...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
