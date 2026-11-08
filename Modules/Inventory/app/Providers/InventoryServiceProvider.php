<?php
namespace Modules\Inventory\Providers;
use Illuminate\Support\ServiceProvider;
class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(module_path('Inventory', 'routes/web.php'));
        $this->loadMigrationsFrom(module_path('Inventory', 'database/migrations'));
    }
}
