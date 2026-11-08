<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    private ModuleRegistryService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ModuleRegistryService::class);
    }
    public function test_foundation_module_is_discoverable()
    {
        $this->assertNotNull(Module::find('Foundation'));
        $this->assertTrue(Module::isEnabled('Foundation'));
    }

    public function test_foundation_routes_load()
    {
        $this->assertTrue(Route::has('admin.dashboard'));
        $this->assertTrue(Route::has('admin.branches.index'));
    }

    public function test_module_registry_describes_modules()
    {
        $this->svc->sync();
        $modules = $this->svc->all();
        $foundation = $modules->firstWhere('name', 'Foundation');
        $this->assertEquals('foundation', $foundation->identifier);
        $this->assertTrue($foundation->is_protected);
        $this->assertTrue($modules->pluck('identifier')->unique()->count() === $modules->count());
    }

    public function test_foundation_cannot_be_disabled_via_registry()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->sync();
        $this->svc->setEnabled('Foundation', false);
    }

    public function test_middleware_is_applied_to_admin_routes()
    {
        $route = Route::getRoutes()->getByName('admin.dashboard');
        $this->assertContains('account.active', $route->middleware());
        $this->assertContains('module.enabled:Foundation', $route->middleware());
    }

    public function test_existing_routes_remain_functional()
    {
        $this->assertTrue(Route::has('home'));
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('announcements'));
    }
}
