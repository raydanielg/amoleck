<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\HospitalOperations\Models\Department;
use Modules\HospitalOperations\Models\ServiceCategory;
use Modules\HospitalOperations\Services\ServiceCatalogueService;
use Tests\TestCase;

class ServiceCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Branch $hq; private Branch $b2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        $this->b2 = Branch::create(['name' => 'B2', 'code' => 'B2']);
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'hospitaloperations')->update(['status' => 'active']);
    }

    public function test_department_hierarchy_and_circular_guard()
    {
        $svc = app(ServiceCatalogueService::class);
        $parent = $svc->createDepartment(['code' => 'PHY', 'name' => 'Physiotherapy'], $this->admin->id);
        $child = $svc->createDepartment(['code' => 'NEU', 'name' => 'Neuro Rehab', 'parent_id' => $parent->id], $this->admin->id);
        $this->assertEquals($parent->id, $child->parent_id);
        // parent cannot become its own descendant
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->updateDepartment($parent, ['parent_id' => $child->id], $this->admin->id);
    }

    public function test_department_branch_assignment()
    {
        $d = app(ServiceCatalogueService::class)->createDepartment(['code' => 'PHY', 'name' => 'Physio'], $this->admin->id);
        app(ServiceCatalogueService::class)->syncDepartmentBranches($d, [$this->hq->id, $this->b2->id]);
        $this->assertEquals(2, $d->branches()->count());
    }

    public function test_service_lifecycle_and_versions()
    {
        $svc = app(ServiceCatalogueService::class);
        $s = $svc->createService(['code' => 'IPA', 'name' => 'Initial Assessment', 'default_duration' => 45], $this->admin->id);
        $this->assertEquals('draft', $s->status);
        $svc->transitionService($s, 'active', $this->admin->id);
        $this->assertEquals('active', $s->fresh()->status);
        $svc->transitionService($s, 'inactive', $this->admin->id);
        $this->assertEquals(3, $s->versions()->count());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transitionService($s, 'draft', $this->admin->id);
    }

    public function test_branch_availability()
    {
        $svc = app(ServiceCatalogueService::class);
        $s = ClinicalService::create(['code' => 'IPA', 'name' => 'X', 'status' => 'active']);
        $this->assertTrue($svc->availableAt($s, $this->hq->id));
        $svc->setBranchAvailability($s, $this->b2->id, false);
        $this->assertFalse($svc->availableAt($s->fresh(), $this->b2->id));
        $this->assertTrue($svc->availableAt($s->fresh(), $this->hq->id));
    }

    public function test_eligible_clinicians()
    {
        $s = ClinicalService::create(['code' => 'IPA', 'name' => 'X', 'status' => 'active']);
        $physio = User::factory()->create();
        $s->clinicians()->create(['user_id' => $physio->id, 'branch_id' => $this->hq->id, 'active' => true]);
        $s->clinicians()->create(['user_id' => User::factory()->create()->id, 'branch_id' => $this->b2->id, 'active' => true]);
        $s->clinicians()->create(['user_id' => User::factory()->create()->id, 'branch_id' => null, 'active' => false]);
        $eligible = app(ServiceCatalogueService::class)->eligibleClinicians($s, $this->hq->id);
        $this->assertEquals(1, $eligible->count()); // HQ + no global active
    }

    public function test_workflow_mapping_validated()
    {
        $this->actingAs($this->admin)->post(route('admin.services.store'), ['code' => 'X', 'name' => 'X', 'workflow_mapping' => 'bogus']);
        $this->assertEquals(0, ClinicalService::count()); // invalid workflow rejected
    }

    public function test_category_management()
    {
        $this->actingAs($this->admin)->post(route('admin.service-categories.store'), ['code' => 'PT', 'name' => 'Physiotherapy']);
        $this->assertEquals(1, ServiceCategory::count());
    }

    public function test_unauthorized_cannot_manage()
    {
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->actingAs($staff)->post(route('admin.departments.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.services.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }
}
