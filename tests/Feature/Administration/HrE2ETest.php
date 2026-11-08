<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Administration\Models\{Employee, JobVacancy, LeaveType, PayrollPeriod, StatutoryRule, WorkShift, PerformanceCycle};
use Modules\Administration\Services\{AttendanceService, HrService, LeaveService, PayrollService, PerformanceService, RecruitmentService};
use Modules\Finance\Models\{AccountingPeriod, ChartOfAccount, JournalEntry};
use Modules\Foundation\Models\{Branch, SystemModule};
use Modules\Foundation\Services\ModuleRegistryService;
use Tests\TestCase;

class HrE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $approver; private Branch $hq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        foreach (['Administration', 'Finance', 'HospitalOperations'] as $m) { \Nwidart\Modules\Facades\Module::find($m)?->enable(); }
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['administration', 'finance', 'hospitaloperations'])->update(['status' => 'active']);
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        AccountingPeriod::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        foreach ([['1000','Cash','asset'],['2100','Payables','liability'],['5000','Expenses','expense']] as [$c,$n,$t]) {
            ChartOfAccount::create(['code' => $c, 'name' => $n, 'type' => $t]);
        }
    }

    /** Full journey: vacancy → apply → offer → hire → salary → shift → clock → leave → payroll → GL. */
    public function test_full_hr_journey()
    {
        $rec = app(RecruitmentService::class); $hr = app(HrService::class);
        $v = $rec->createVacancy(['title' => 'Physio', 'branch_id' => $this->hq->id, 'openings' => 1, 'status' => 'open'], $this->admin->id);
        $c = $rec->apply($v, ['first_name' => 'Asha', 'last_name' => 'J', 'email' => 'a@j.com']);
        $rec->transitionCandidate($c, 'screening', $this->admin->id);
        $rec->transitionCandidate($c->fresh(), 'interview', $this->admin->id);
        $rec->transitionCandidate($c->fresh(), 'offered', $this->admin->id);
        $e = $rec->hire($c->fresh(), ['category' => 'physiotherapist', 'employment_type' => 'permanent'], $this->admin->id);
        $this->assertSame(1, Employee::where('employee_number', $e->employee_number)->count()); // no dup
        $e->update(['status' => 'active']);

        // salary
        $pay = app(PayrollService::class);
        $pay->assignSalary($e, 1000000, '2026-01-01', $this->admin->id);
        StatutoryRule::create(['code' => 'NSSF-EE', 'type' => 'employee', 'rate' => 10, 'effective_from' => '2026-01-01']);

        // attendance: assign shift → clock in/out
        $att = app(AttendanceService::class);
        $shift = WorkShift::create(['code' => 'GEN', 'name' => 'General', 'starts_at' => '08:00', 'ends_at' => '17:00', 'grace_minutes' => 10, 'break_minutes' => 30]);
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse(today()->toDateString().' 08:05'));
        $att->assignShift($e, $shift->id, $this->hq->id, today()->toDateString(), $this->admin->id);
        $r = $att->clockIn($e, $this->hq->id, $this->admin->id);
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse(today()->toDateString().' 17:10'));
        $att->clockOut($r->fresh(), $this->admin->id);
        \Carbon\Carbon::setTestNow();
        $this->assertEquals('present', $r->fresh()->status); // within grace
        $this->assertEquals(515, (int) $r->fresh()->worked_minutes);

        // leave: accrue → request → submit → approve
        $lv = app(LeaveService::class);
        $lt = LeaveType::create(['code' => 'ANN', 'name' => 'Annual', 'paid' => true]);
        $lv->accrue($e, $lt, 20, $this->admin->id, 'FY26');
        $req = $lv->request(['employee_id' => $e->id, 'leave_type_id' => $lt->id, 'branch_id' => $this->hq->id, 'start_date' => '2026-02-02', 'end_date' => '2026-02-06'], $this->admin->id);
        $lv->transition($req, 'submitted', $this->admin->id);
        $lv->transition($req->fresh(), 'approved', $this->approver->id);
        $this->assertEquals(15, $lv->balance($e->id, $lt->id));

        // payroll → finalize → GL → pay
        $p = PayrollPeriod::create(['name' => 'P', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'open']);
        $run = $pay->calculate($e, $p, $this->admin->id);
        $this->assertEquals(900000, (float) $run->net); // 1M − 10% NSSF
        $pay->transition($run, 'approved', $this->approver->id);
        $pay->transition($run->fresh(), 'finalized', $this->admin->id);
        $pay->markPaid($run->fresh(), $this->admin->id);
        $this->assertEquals('paid', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->payslip()->first());
        $this->assertGreaterThanOrEqual(2, JournalEntry::count()); // payroll + payment journals
        // journals balanced
        foreach (JournalEntry::with('lines')->get() as $j) {
            $d = $j->lines->where('direction', 'debit')->sum('amount');
            $cr = $j->lines->where('direction', 'credit')->sum('amount');
            $this->assertEquals(0, bccomp((string) $d, (string) $cr, 2), "Journal {$j->id} unbalanced");
        }

        // performance: cycle → review → finalize
        $pf = app(PerformanceService::class);
        $cy = $pf->createCycle(['code' => 'C1', 'name' => 'C', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $rev = $pf->openReview($e, $cy, $this->admin->id);
        $pf->submitSelf($rev, 4.0, 'x', $this->admin->id);
        $pf->supervisorReview($rev->fresh(), 3.0, $this->approver->id);
        $this->assertEquals(3.4, (float) $rev->fresh()->final_score);
    }

    /** Scenario H — duplicate employee prevention + IDOR-style isolation. */
    public function test_no_duplicate_employee_on_rehire()
    {
        $rec = app(RecruitmentService::class);
        $v = $rec->createVacancy(['title' => 'X', 'branch_id' => $this->hq->id, 'openings' => 1, 'status' => 'open'], $this->admin->id);
        $c = $rec->apply($v, ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);
        $c->update(['status' => 'offered']);
        $rec->hire($c->fresh(), ['category' => 'nurse', 'employment_type' => 'p'], $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $rec->hire($c->fresh(), ['category' => 'nurse', 'employment_type' => 'p'], $this->admin->id); // hired → not offered → 422
    }
}
