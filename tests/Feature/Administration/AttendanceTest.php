<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Administration\Models\AttendanceRecord;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\ShiftAssignment;
use Modules\Administration\Models\WorkShift;
use Modules\Administration\Services\AttendanceService;
use Modules\Administration\Services\HrService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Branch $hq; private Employee $employee; private WorkShift $morning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Administration')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'administration')->update(['status' => 'active']);
        $this->employee = app(HrService::class)->register(['first_name' => 'N', 'last_name' => 'T', 'category' => 'nurse', 'primary_branch_id' => $this->hq->id, 'employment_type' => 'permanent'], $this->admin->id);
        $this->employee->update(['status' => 'active']);
        $this->morning = WorkShift::create(['code' => 'AM', 'name' => 'Morning', 'starts_at' => '07:00', 'ends_at' => '15:00', 'grace_minutes' => 10, 'break_minutes' => 30]);
    }

    public function test_shift_assignment_unique_per_day()
    {
        $svc = app(AttendanceService::class);
        $svc->assignShift($this->employee, $this->morning->id, $this->hq->id, today()->toDateString(), $this->admin->id);
        $this->assertEquals(1, ShiftAssignment::count());
        $svc->assignShift($this->employee, $this->morning->id, $this->hq->id, today()->toDateString(), $this->admin->id);
        $this->assertEquals(1, ShiftAssignment::count()); // updateOrCreate — same day, same employee
    }

    public function test_clock_in_and_out()
    {
        $svc = app(AttendanceService::class);
        $r = $svc->clockIn($this->employee, $this->hq->id, $this->admin->id);
        $this->assertNotNull($r->clock_in);
        $this->assertEquals('present', $r->status);
        $r = $svc->clockOut($r, $this->admin->id);
        $this->assertNotNull($r->clock_out);
        $this->assertNotNull($r->worked_minutes);
    }

    public function test_late_arrival_detected()
    {
        $svc = app(AttendanceService::class);
        $svc->assignShift($this->employee, $this->morning->id, $this->hq->id, today()->toDateString(), $this->admin->id);
        Carbon::setTestNow(Carbon::parse(today()->toDateString().' '.$this->morning->starts_at)->addMinutes(20));
        $r = $svc->clockIn($this->employee, $this->hq->id, $this->admin->id);
        Carbon::setTestNow();
        $this->assertEquals(10, $r->late_minutes); // 07:00+10 grace = 07:10; in at 07:20 → 10 late
        $this->assertEquals('late', $r->status);
    }

    public function test_overnight_shift_end()
    {
        $night = WorkShift::create(['code' => 'NT', 'name' => 'Night', 'starts_at' => '23:00', 'ends_at' => '07:00', 'overnight' => true]);
        $shiftEnd = Carbon::parse(today()->toDateString().' '.$night->ends_at);
        if ($night->overnight) { $shiftEnd->addDay(); }
        $this->assertEquals(today()->addDay()->toDateString(), $shiftEnd->toDateString()); // 07:00 next day
    }

    public function test_duplicate_clock_in_rejected()
    {
        $svc = app(AttendanceService::class);
        $svc->clockIn($this->employee, $this->hq->id, $this->admin->id);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $svc->clockIn($this->employee, $this->hq->id, $this->admin->id);
    }

    public function test_double_clock_out_rejected()
    {
        $svc = app(AttendanceService::class);
        $r = $svc->clockIn($this->employee, $this->hq->id, $this->admin->id);
        $svc->clockOut($r, $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->clockOut($r->fresh(), $this->admin->id);
    }

    public function test_correction_workflow()
    {
        $svc = app(AttendanceService::class);
        $r = $svc->clockIn($this->employee, $this->hq->id, $this->admin->id);
        $c = $svc->requestCorrection($r, 'clock_in', now()->subHour()->toDateTimeString(), 'Forgot to clock in', $this->employee->user_id ?? $this->admin->id);
        $svc->approveCorrection($c, 'approve', $this->admin->id);
        $this->assertEquals('approved', $c->fresh()->status);
        $this->assertEquals($c->new_value, $r->fresh()->clock_in->toDateTimeString());
    }

    public function test_absence_candidates()
    {
        $svc = app(AttendanceService::class);
        $svc->assignShift($this->employee, $this->morning->id, $this->hq->id, today()->toDateString(), $this->admin->id);
        $this->assertEquals(1, $svc->absenceCandidates($this->hq->id)->count()); // scheduled, no clock-in
    }

    public function test_period_summary_payroll_ready()
    {
        $svc = app(AttendanceService::class);
        $svc->assignShift($this->employee, $this->morning->id, $this->hq->id, today()->toDateString(), $this->admin->id);
        Carbon::setTestNow(Carbon::parse(today()->toDateString().' '.$this->morning->starts_at));
        $r = $svc->clockIn($this->employee, $this->hq->id, $this->admin->id);
        Carbon::setTestNow(Carbon::parse(today()->toDateString().' '.$this->morning->ends_at));
        $svc->clockOut($r, $this->admin->id);
        Carbon::setTestNow();
        $sum = $svc->periodSummary($this->employee->id, today()->subDay()->toDateString(), today()->toDateString());
        $this->assertEquals(1, $sum['worked_days']);
        $this->assertGreaterThan(0, $sum['worked_minutes']);
    }
}
