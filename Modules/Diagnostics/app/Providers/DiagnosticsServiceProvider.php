<?php
namespace Modules\Diagnostics\Providers;
use Illuminate\Support\ServiceProvider;
class DiagnosticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(module_path('Diagnostics', 'routes/web.php'));
        $this->loadMigrationsFrom(module_path('Diagnostics', 'database/migrations'));
    }
}
