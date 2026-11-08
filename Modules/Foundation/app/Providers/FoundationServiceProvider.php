<?php

namespace Modules\Foundation\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FoundationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Route::middleware(['web'])
            ->group(__DIR__.'/../../routes/web.php');

        if (file_exists(__DIR__.'/../../routes/api.php')) {
            Route::middleware(['api'])
                ->prefix('api')
                ->group(__DIR__.'/../../routes/api.php');
        }

        Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin') ? true : null);
        Gate::policy(\Modules\Foundation\Models\Branch::class, \Modules\Foundation\Policies\BranchPolicy::class);
    }

    public function register(): void {}
}
