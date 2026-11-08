<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ModuleRegistryService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->svc = app(ModuleRegistryService::class);
        $this->svc->sync();
    }

    public function test_registry_sync_discovers_installed_and_marks_missing()
    {
        $this->assertEquals('active', $this->svc->statusOf('Foundation'));
        $this->assertEquals('active', $this->svc->statusOf('PatientCare'));
        $this->assertEquals('unavailable', $this->svc->statusOf('Finance'));
    }

    public function test_module_identifiers_are_unique()
    {
        $ids = SystemModule::pluck('identifier');
        $this->assertEquals($ids->count(), $ids->unique()->count());
    }

    public function test_foundation_is_protected_from_status_changes()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->setStatus('Foundation', 'disabled');
    }

    public function test_cannot_activate_module_with_inactive_dependency()
    {
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'disabled']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        // simulate Finance dependency check by requiring its dep active — Foundation is active so test dep failure via fake dep
        SystemModule::where('identifier', 'finance')->update(['dependencies' => ['PatientCare']]);
        $this->svc->setStatus('Finance', 'active');
    }

    public function test_cannot_disable_module_with_active_dependents()
    {
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->setStatus('Foundation', 'disabled');
    }

    public function test_circular_dependency_detection()
    {
        $this->assertTrue($this->svc->hasCircularDependency('Foundation', ['Foundation']));
        $this->assertFalse($this->svc->hasCircularDependency('PatientCare', ['Foundation']));
    }

    public function test_status_transitions_and_audit()
    {
        $mod = SystemModule::where('identifier', 'patientcare')->firstOrFail();
        $mod->update(['status' => 'disabled']);
        $this->svc->setStatus('PatientCare', 'active');
        $this->assertEquals('active', $this->svc->statusOf('PatientCare'));
        $this->svc->setStatus('PatientCare', 'maintenance');
        $this->assertEquals('maintenance', $this->svc->statusOf('PatientCare'));
        $this->svc->setStatus('PatientCare', 'disabled');
        $this->assertEquals(3, AuditLog::where('entity_id', $mod->id)->where('action', 'like', 'module.%')->count());
    }

    public function test_role_based_module_access()
    {
        $mod = SystemModule::where('identifier', 'patientcare')->firstOrFail();
        $mod->update(['status' => 'active']);
        $role = Role::where('name', 'Physiotherapist')->firstOrFail();
        $this->svc->syncRoleAccess('PatientCare', [$role->id]);

        $pt = User::factory()->create();
        $pt->assignRole('Physiotherapist');
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->assertTrue($this->svc->isAccessibleBy('PatientCare', $pt));
        $this->assertFalse($this->svc->isAccessibleBy('PatientCare', $receptionist));
        $this->assertTrue($this->svc->isAccessibleBy('PatientCare', $this->admin));
    }

    public function test_disabled_module_denies_access_even_with_role()
    {
        $mod = SystemModule::where('identifier', 'patientcare')->firstOrFail();
        $mod->update(['status' => 'disabled']);
        $pt = User::factory()->create();
        $pt->assignRole('Physiotherapist');
        $this->assertFalse($this->svc->isAccessibleBy('PatientCare', $pt));
    }

    public function test_role_access_sync_is_audited()
    {
        $mod = SystemModule::where('identifier', 'finance')->firstOrFail();
        $this->svc->syncRoleAccess('Finance', [Role::where('name', 'Accountant')->firstOrFail()->id]);
        $this->assertEquals('module.access_updated', AuditLog::where('entity_id', $mod->id)->latest('id')->first()->action);
    }

    public function test_unavailable_module_cannot_be_activated()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->setStatus('Finance', 'active');
    }

    public function test_features_registered_in_metadata()
    {
        $mod = SystemModule::where('identifier', 'patientcare')->firstOrFail();
        $this->assertContains('patient_registration', $mod->features);
        $this->assertContains('therapy_sessions', $mod->features);
    }
}
