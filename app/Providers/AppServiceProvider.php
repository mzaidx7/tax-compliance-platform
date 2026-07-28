<?php

namespace App\Providers;

use App\Contracts\MalwareScanner;
use App\Support\FeatureFlags;
use App\Support\OperationalHealth;
use App\Support\UnavailableMalwareScanner;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantCache;
use App\Tenancy\TenantNamespace;
use App\Tenancy\TenantStorage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FirmContext::class);
        $this->app->scoped(TenantNamespace::class);
        $this->app->scoped(TenantCache::class);
        $this->app->scoped(TenantStorage::class);
        $this->app->singleton(FeatureFlags::class);
        $this->app->singleton(OperationalHealth::class);
        $this->app->bind(MalwareScanner::class, UnavailableMalwareScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
