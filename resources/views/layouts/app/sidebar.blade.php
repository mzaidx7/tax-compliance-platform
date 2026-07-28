<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        @php
            $firmContext = app(\App\Tenancy\FirmContext::class);
            $hasFirmContext = $firmContext->hasFirm();
        @endphp

        <flux:sidebar sticky collapsible="mobile" class="border-e border-white/8 bg-zinc-900">
            <flux:sidebar.header class="border-b border-white/8 pb-4">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if ($hasFirmContext)
                <div class="px-2 py-4">
                    <x-firm-switcher />
                </div>
            @else
                <div class="px-3 py-5">
                    <p class="text-xs font-medium text-zinc-500">{{ __('Personal account') }}</p>
                    <p class="mt-1 text-sm text-zinc-300">{{ __('Account settings') }}</p>
                </div>
            @endif

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Workspace')" class="grid">
                    @if ($hasFirmContext)
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>

                        @if (
                            app(\App\Support\FeatureFlags::class)->enabled(
                                \App\Enums\Feature::ClientMaster,
                                $firmContext->firmId(),
                            )
                        )
                            @can('viewAny', \App\Models\Client::class)
                                <flux:sidebar.item icon="building-office-2" :href="route('clients.index')" :current="request()->routeIs('clients.*')" wire:navigate>
                                    {{ __('Clients') }}
                                </flux:sidebar.item>
                                <flux:sidebar.item icon="document-text" :href="route('documents.index')" :current="request()->routeIs('documents.index')" wire:navigate>
                                    {{ __('Document expiry') }}
                                </flux:sidebar.item>
                            @endcan
                        @endif

                        @if (
                            app(\App\Support\FeatureFlags::class)->enabled(
                                \App\Enums\Feature::EInvoicingReadiness,
                                $firmContext->firmId(),
                            )
                        )
                            @can('viewAny', \App\Models\DataQualityRuleDefinition::class)
                                <flux:sidebar.item icon="circle-stack" :href="route('readiness.rules.index')" :current="request()->routeIs('readiness.*')" wire:navigate>
                                    {{ __('Readiness rules') }}
                                </flux:sidebar.item>
                            @endcan
                        @endif

                        @if (
                            app(\App\Support\FeatureFlags::class)->enabled(
                                \App\Enums\Feature::ComplianceOperations,
                                $firmContext->firmId(),
                            )
                        )
                            @can('viewAny', \App\Models\Obligation::class)
                                <flux:sidebar.item icon="calendar-days" :href="route('obligations.index')" :current="request()->routeIs('obligations.*')" wire:navigate>
                                    {{ __('Obligations') }}
                                </flux:sidebar.item>
                                <flux:sidebar.item icon="book-open" :href="route('rules.index')" :current="request()->routeIs('rules.*')" wire:navigate>
                                    {{ __('Rule governance') }}
                                </flux:sidebar.item>
                                <flux:sidebar.item icon="sparkles" :href="route('generation.index')" :current="request()->routeIs('generation.*')" wire:navigate>
                                    {{ __('Generation preview') }}
                                </flux:sidebar.item>
                            @endcan

                            @can('viewAny', \App\Models\WorkItem::class)
                                <flux:sidebar.item icon="queue-list" :href="route('work-items.index')" :current="request()->routeIs('work-items.*')" wire:navigate>
                                    {{ __('Work register') }}
                                </flux:sidebar.item>
                            @endcan
                        @endif

                        @if (
                            app(\App\Support\FeatureFlags::class)->enabled(
                                \App\Enums\Feature::AuditViewer,
                                $firmContext->firmId(),
                            )
                        )
                            @can('viewAny', \App\Models\AuditLog::class)
                                <flux:sidebar.item icon="shield-check" :href="route('audit.index')" :current="request()->routeIs('audit.*')" wire:navigate>
                                    {{ __('Audit register') }}
                                </flux:sidebar.item>
                            @endcan
                        @endif

                        @can('viewAny', \App\Models\FirmMembership::class)
                            <flux:sidebar.item icon="users" :href="route('members.index')" :current="request()->routeIs('members.*')" wire:navigate>
                                {{ __('Members') }}
                            </flux:sidebar.item>
                        @endcan
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav class="border-t border-white/8 pt-4">
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*', 'appearance.*', 'security.*')" wire:navigate>
                    {{ __('Account settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="border-b border-white/8 bg-zinc-950 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <span class="ml-3 text-sm font-semibold text-zinc-200">
                {{ $hasFirmContext ? $firmContext->firm()->name : __('Account settings') }}
            </span>
            <flux:spacer />
            <flux:profile
                :initials="auth()->user()->initials()"
                :href="route('profile.edit')"
            />
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
