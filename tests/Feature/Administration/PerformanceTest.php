<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\PerformanceCycle;
use Modules\Administration\Models\PerformanceKpi;
use Modules\Administration\Models\PerformanceReview;
use Modules\Administration\Services\HrService;
use Modules\Administration\Services\PerformanceService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Department;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $supervisor; private Branch $hq; private Employee $employee; private PerformanceCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Administration')?->enable();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['administration', 'hospitaloperations'])->update(['status' => 'active']);
        $this->supervisor = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->employee = app(HrService::class)->register(['first_name' => 'P', 'last_name' => 'F', 'category' => 'nurse', 'primary_branch_id' => $this->hq->id, 'employment_type' => 'permanent'], $this->admin->id);
        $this->employee->update(['status' => 'active']);
        $this->cycle = app(PerformanceService::class)->createCycle(['code' => '2026-ANN', 'name' => 'FY2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    }

    public function test_cycle_and_kpi()
    {
        PerformanceKpi::create(['code' => 'DOC', 'name' => 'Documentation', 'category' => 'clinical', 'department_id' => Department::create(['code' => 'PHY', 'name' => 'Phy'])->id, 'weight' => 1.5, 'target' => 95]);
        $this->assertEquals(1, PerformanceKpi::count());
    }

    public function test_objective_assignment()
    {
        $kpi = PerformanceKpi::create(['code' => 'DOC', 'name' => 'Documentation', 'category' => 'clinical']);
        $obj = app(PerformanceService::class)->assignObjective($this->employee, $kpi->id, ['target' => 90, 'due_date' => '2026-06-30'], $this->admin->id);
        $this->assertEquals('assigned', $obj->status);
    }

    public function test_review_lifecycle()
    {
        $svc = app(PerformanceService::class);
        $r = $svc->openReview($this->employee, $this->cycle, $this->admin->id);
        $svc->submitSelf($r, 4.0, 'Strong year', $this->admin->id);
        $svc->supervisorReview($r->fresh(), 3.0, $this->supervisor->id, 'Solid work');
        $r = $r->fresh();
        $this->assertEquals(3.4, (float) $r->final_score); // 4×0.4 + 3×0.6
        $this->assertEquals('supervisor_reviewed', $r->status);
    }

    public function test_score_out_of_range_rejected()
    {
        $r = app(PerformanceService::class)->openReview($this->employee, $this->cycle, $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PerformanceService::class)->submitSelf($r, 6.0, 'x', $this->admin->id);
    }

    public function test_self_review_rejected()
    {
        $r = app(PerformanceService::class)->openReview($this->employee, $this->cycle, $this->admin->id);
        app(PerformanceService::class)->submitSelf($r, 4.0, 'x', $this->admin->id);
        $this->employee->update(['user_id' => $this->admin->id]); // link supervisor to self
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PerformanceService::class)->supervisorReview($r->fresh(), 3.0, $this->admin->id);
    }

    public function test_unique_review_per_cycle()
    {
        $svc = app(PerformanceService::class);
        $svc->openReview($this->employee, $this->cycle, $this->admin->id);
        $r2 = $svc->openReview($this->employee, $this->cycle, $this->admin->id); // same cycle — firstOrCreate
        $this->assertEquals(1, PerformanceReview::count());
    }

    public function test_finalization_and_acknowledgement()
    {
        $svc = app(PerformanceService::class);
        $r = $svc->openReview($this->employee, $this->cycle, $this->admin->id);
        $svc->submitSelf($r, 4.0, 'x', $this->admin->id);
        $svc->supervisorReview($r->fresh(), 3.0, $this->supervisor->id);
        $svc->transition($r->fresh(), 'hr_approved', $this->admin->id);
        $svc->transition($r->fresh(), 'finalized', $this->admin->id);
        $r = $r->fresh();
        $this->assertEquals('finalized', $r->status);
        $svc->acknowledge($r, 'I disagree with section 2');
        $this->assertEquals('I disagree with section 2', $r->fresh()->disagreement);
    }

    public function test_pip_creation()
    {
        $pip = app(PerformanceService::class)->createPip($this->employee, null, ['objective' => 'Improve documentation', 'start_date' => today()], $this->admin->id);
        $this->assertMatchesRegularExpression('/^PIP-\d{4}-\d{6}$/', $pip->pip_number);
        $this->assertEquals('draft', $pip->status);
    }
}
