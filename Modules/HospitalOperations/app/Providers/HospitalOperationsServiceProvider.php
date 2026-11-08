<?php

namespace Modules\HospitalOperations\Providers;

use Illuminate\Support\ServiceProvider;

class HospitalOperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'queue');
    }
}
