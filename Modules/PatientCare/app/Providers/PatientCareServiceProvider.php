<?php

namespace Modules\PatientCare\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PatientCareServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'clinical');
        Route::middleware(['web'])->group(__DIR__.'/../../routes/web.php');
    }

    public function register(): void {}
}
