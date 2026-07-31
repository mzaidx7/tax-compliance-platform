<div class="tbt-panel flex items-start p-4 max-md:flex-col md:p-5">
    <div class="me-8 w-full pb-4 md:w-[220px] md:border-r md:border-[var(--tbt-border)] md:pr-5">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
            @can('viewAny', \App\Models\FeatureFlagOverride::class)
                <flux:navlist.item :href="route('settings.features')" wire:navigate>{{ __('Features') }}</flux:navlist.item>
            @endcan
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6 md:pl-1">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-xl">
            {{ $slot }}
        </div>
    </div>
</div>
