<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\LeaveRequest;
use Modules\Administration\Models\LeaveType;
use Modules\Administration\Models\PublicHoliday;
use Modules\Administration\Services\HrService;
use Modules\Administration\Services\LeaveService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $approver; private Branch $hq; private Employee $employee; private LeaveType $annual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Administration')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'administration')->update(['status' => 'active']);
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->employee = app(HrService::class)->register(['first_name' => 'L', 'last_name' => 'T', 'category' => 'nurse', 'primary_branch_id' => $this->hq->id, 'employment_type' => 'permanent'], $this->admin->id);
        $this->employee->update(['status' => 'active']);
        $this->annual = LeaveType::create(['code' => 'ANN', 'name' => 'Annual', 'paid' => true]);
    }

    private function requestLeave(string $start, string $end): LeaveRequest
    {
        return app(LeaveService::class)->request(['employee_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'branch_id' => $this->hq->id, 'start_date' => $start, 'end_date' => $end], $this->admin->id);
    }

    public function test_leave_request_created_and_numbered()
    {
        $r = $this->requestLeave('2026-01-05', '2026-01-09'); // Mon-Fri
        $this->assertMatchesRegularExpression('/^LR-\d{4}-\d{6}$/', $r->request_number);
        $this->assertEquals(5, $r->days);
        $this->assertEquals('draft', $r->status);
    }

    public function test_working_days_skip_weekends()
    {
        $r = $this->requestLeave('2026-01-03', '2026-01-12'); // Sat-Sun spanning → 6 working days (Jan 3-4 weekend, Jan 10-11 weekend → Jan 5-9 = 5 + Jan 12 = 6? Jan 12 is Monday)
        $this->assertEquals(6, $r->days); // Jan 5,6,7,8,9,12
    }

    public function test_public_holiday_excluded()
    {
        PublicHoliday::create(['name' => 'New Year', 'date' => '2026-01-05']);
        $r = $this->requestLeave('2026-01-05', '2026-01-09');
        $this->assertEquals(4, $r->days); // Jan 5 is a holiday
    }

    public function test_submission_reserves_balance()
    {
        $svc = app(LeaveService::class);
        $svc->accrue($this->employee, $this->annual, 20, $this->admin->id, 'FY26-ANN');
        $r = $this->requestLeave('2026-01-05', '2026-01-09');
        $svc->transition($r, 'submitted', $this->admin->id);
        $this->assertEquals(15, $svc->balance($this->employee->id, $this->annual->id)); // 20 - 5 reserved
    }

    public function test_approval_workflow()
    {
        $svc = app(LeaveService::class);
        $svc->accrue($this->employee, $this->annual, 20, $this->admin->id, 'FY26-ANN');
        $r = $this->requestLeave('2026-01-05', '2026-01-09');
        $svc->transition($r, 'submitted', $this->admin->id);
        $svc->transition($r->fresh(), 'approved', $this->approver->id);
        $this->assertEquals('approved', $r->fresh()->status);
    }

    public function test_self_approval_rejected()
    {
        $svc = app(LeaveService::class);
        $svc->accrue($this->employee, $this->annual, 20, $this->admin->id, 'FY26-ANN');
        $r = $this->requestLeave('2026-01-05', '2026-01-09');
        $r->update(['status' => 'submitted']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transition($r->fresh(), 'approved', $this->admin->id); // created_by == approver
    }

    public function test_rejection_releases_reservation()
    {
        $svc = app(LeaveService::class);
        $svc->accrue($this->employee, $this->annual, 20, $this->admin->id, 'FY26-ANN');
        $r = $this->requestLeave('2026-01-05', '2026-01-09');
        $svc->transition($r, 'submitted', $this->admin->id);
        $svc->transition($r->fresh(), 'rejected', $this->approver->id, 'Staffing need');
        $this->assertEquals(20, $svc->balance($this->employee->id, $this->annual->id)); // released back
    }

    public function test_insufficient_balance_rejected()
    {
        $svc = app(LeaveService::class);
        $svc->accrue($this->employee, $this->annual, 3, $this->admin->id, 'FY26-ANN');
        $r = $this->requestLeave('2026-01-05', '2026-01-09'); // 5 days, only 3 accrued
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transition($r, 'submitted', $this->admin->id);
    }

    public function test_duplicate_accrual_rejected()
    {
        $svc = app(LeaveService::class);
        $svc->accrue($this->employee, $this->annual, 20, $this->admin->id, 'FY26-ANN');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->accrue($this->employee, $this->annual, 20, $this->admin->id, 'FY26-ANN'); // same reference
    }

    public function test_on_leave_integration()
    {
        $svc = app(LeaveService::class);
        $svc->accrue($this->employee, $this->annual, 20, $this->admin->id, 'FY26-ANN');
        $r = $this->requestLeave(today()->toDateString(), today()->addDays(3)->toDateString()); // spans a weekday
        $svc->transition($r, 'submitted', $this->admin->id);
        $svc->transition($r->fresh(), 'approved', $this->approver->id);
        $this->assertEquals(1, $svc->onLeaveToday($this->hq->id)->count());
    }
}
