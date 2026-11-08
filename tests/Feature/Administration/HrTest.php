<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\JobPosition;
use Modules\Administration\Services\HrService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Department;
use Tests\TestCase;

class HrTest extends TestCase
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
        \Nwidart\Modules\Facades\Module::find('Administration')?->enable();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['administration', 'hospitaloperations'])->update(['status' => 'active']);
    }

    private function employee(): Employee
    {
        return app(HrService::class)->register(['first_name' => 'Jane', 'last_name' => 'Doe', 'category' => 'nurse', 'primary_branch_id' => $this->hq->id, 'employment_type' => 'permanent'], $this->admin->id);
    }

    public function test_registration_and_numbering()
    {
        $e = $this->employee();
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{6}$/', $e->employee_number);
        $this->assertEquals('pending', $e->status);
        $this->assertEquals(1, $e->assignments()->count()); // auto primary-branch assignment
    }

    public function test_lifecycle_transition()
    {
        $e = $this->employee();
        app(HrService::class)->transition($e, 'active', $this->admin->id);
        $this->assertEquals('active', $e->fresh()->status);
        $this->assertEquals(2, $e->statusHistories()->count());
    }

    public function test_invalid_transition_rejected()
    {
        $e = $this->employee();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(HrService::class)->transition($e, 'retired', $this->admin->id); // pending→retired not allowed
    }

    public function test_termination_requires_reason()
    {
        $e = $this->employee();
        $e->update(['status' => 'active']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(HrService::class)->transition($e, 'terminated', $this->admin->id); // no reason
    }

    public function test_transfer_closes_old_assignment()
    {
        $e = $this->employee();
        $e->update(['status' => 'active']);
        app(HrService::class)->transfer($e, $this->b2->id, null, $this->admin->id, 'HQ rotation');
        $e = $e->fresh();
        $this->assertEquals($this->b2->id, $e->primary_branch_id);
        $this->assertEquals(1, $e->assignments()->where('status', 'active')->count());
        $this->assertEquals(1, $e->assignments()->where('status', 'ended')->count());
    }

    public function test_pending_employee_cannot_transfer()
    {
        $e = $this->employee();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(HrService::class)->transfer($e, $this->b2->id, null, $this->admin->id);
    }

    public function test_contract_and_renewal()
    {
        $e = $this->employee();
        $c = app(HrService::class)->createContract($e, ['type' => 'fixed', 'job_title' => 'Nurse', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'], $this->admin->id);
        $this->assertMatchesRegularExpression('/^CT-\d{4}-\d{6}$/', $c->contract_number);
        $c->update(['status' => 'active']);
        $new = app(HrService::class)->renewContract($c->fresh(), ['type' => 'permanent', 'job_title' => 'Nurse', 'start_date' => '2027-01-01'], $this->admin->id);
        $this->assertEquals('renewed', $c->fresh()->status);
        $this->assertEquals('active', $new->status);
        $this->assertEquals(2, $e->contracts()->count()); // history preserved
    }

    public function test_dashboard_counts()
    {
        $this->employee();
        $e2 = $this->employee();
        app(HrService::class)->transition($e2, 'active', $this->admin->id);
        $d = app(HrService::class)->dashboard($this->hq->id);
        $this->assertEquals(2, $d['total']);
        $this->assertEquals(1, $d['active']);
    }

    public function test_position_catalogue()
    {
        JobPosition::create(['code' => 'PHY-SN', 'title' => 'Senior Physiotherapist']);
        $this->assertEquals(1, JobPosition::count());
    }
}
