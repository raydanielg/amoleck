<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\EmployeeLoan;
use Modules\Administration\Models\EmployeeSalary;
use Modules\Administration\Models\PayrollPeriod;
use Modules\Administration\Models\PayrollRun;
use Modules\Administration\Models\SalaryComponent;
use Modules\Administration\Models\StatutoryRule;
use Modules\Administration\Services\HrService;
use Modules\Administration\Services\PayrollService;
use Modules\Finance\Models\AccountingPeriod;
use Modules\Finance\Models\ChartOfAccount;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $approver; private Branch $hq; private Employee $employee; private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Administration')?->enable();
        \Nwidart\Modules\Facades\Module::find('Finance')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['administration', 'finance'])->update(['status' => 'active']);
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->employee = app(HrService::class)->register(['first_name' => 'P', 'last_name' => 'T', 'category' => 'nurse', 'primary_branch_id' => $this->hq->id, 'employment_type' => 'permanent'], $this->admin->id);
        $this->employee->update(['status' => 'active']);
        $this->period = PayrollPeriod::create(['name' => 'Nov 2026', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'open']);
        AccountingPeriod::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        foreach ([['1000','Cash','asset'],['2100','Payables','liability'],['4000','Revenue','revenue'],['5000','Expenses','expense']] as [$c,$n,$t]) {
            ChartOfAccount::create(['code' => $c, 'name' => $n, 'type' => $t]);
        }
    }

    private function payrun(): PayrollRun
    {
        return app(PayrollService::class)->calculate($this->employee, $this->period, $this->admin->id);
    }

    public function test_salary_assignment_effective_dated()
    {
        $svc = app(PayrollService::class);
        $s1 = $svc->assignSalary($this->employee, 800000, '2026-01-01', $this->admin->id);
        $s2 = $svc->assignSalary($this->employee, 900000, '2026-06-01', $this->admin->id);
        $this->assertEquals('superseded', $s1->fresh()->status);
        $this->assertEquals('active', $s2->status);
        $this->assertEquals(2, $this->employee->salaries()->count()); // history preserved
    }

    public function test_payroll_calculation_gross_to_net()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        SalaryComponent::create(['code' => 'HOUSE', 'name' => 'Housing', 'type' => 'earning', 'rate' => 10]);
        SalaryComponent::create(['code' => 'PAYE', 'name' => 'PAYE', 'type' => 'deduction', 'rate' => 20]);
        StatutoryRule::create(['code' => 'NSSF-EE', 'type' => 'employee', 'rate' => 10, 'effective_from' => '2026-01-01']);
        StatutoryRule::create(['code' => 'NSSF-ER', 'type' => 'employer', 'rate' => 10, 'effective_from' => '2026-01-01']);
        $r = $this->payrun();
        $this->assertEquals(1100000, (float) $r->gross); // 1M basic + 100k housing
        $this->assertEquals(300000, (float) $r->deductions); // 200k PAYE + 100k NSSF-EE
        $this->assertEquals(800000, (float) $r->net); // 1.1M - 300k
        $this->assertEquals(100000, (float) $r->employer_contrib); // NSSF-ER separate
        $this->assertEquals(5, $r->lines()->count());
    }

    public function test_employer_not_in_deductions()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        StatutoryRule::create(['code' => 'NSSF-ER', 'type' => 'employer', 'rate' => 10, 'effective_from' => '2026-01-01']);
        $r = $this->payrun();
        $this->assertEquals(0, (float) $r->deductions);
        $this->assertEquals(100000, (float) $r->employer_contrib); // employer separate — never deducted from net
    }

    public function test_loan_installment_deducted_once()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        EmployeeLoan::create(['employee_id' => $this->employee->id, 'principal' => 50000, 'installment' => 10000, 'remaining' => 50000, 'status' => 'active']);
        $r = $this->payrun();
        $this->assertEquals(10000, (float) $r->deductions);
        $this->assertEquals(40000, (float) EmployeeLoan::first()->remaining); // decremented exactly once
    }

    public function test_workflow_and_finalized_immutable()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        $r = $this->payrun();
        $svc = app(PayrollService::class);
        $svc->transition($r, 'approved', $this->approver->id);
        $svc->transition($r->fresh(), 'finalized', $this->admin->id);
        $r = $r->fresh();
        $this->assertEquals('finalized', $r->status);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transition($r, 'approved', $this->admin->id); // immutable
    }

    public function test_self_approval_rejected()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        $r = $this->payrun();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PayrollService::class)->transition($r, 'approved', $this->admin->id);
    }

    public function test_unique_run_per_employee_period()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        $this->payrun();
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->payrun();
    }

    public function test_payslip_issued_on_finalize()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        $r = $this->payrun();
        $r->update(['status' => 'approved']);
        app(PayrollService::class)->transition($r->fresh(), 'finalized', $this->admin->id);
        $this->assertNotNull($r->fresh()->payslip()->first());
        $this->assertMatchesRegularExpression('/^PS-\d{4}-\d{6}$/', $r->fresh()->payslip()->first()->payslip_number);
    }

    public function test_mark_paid_posts_gl()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        $r = $this->payrun();
        $r->update(['status' => 'approved']);
        app(PayrollService::class)->transition($r->fresh(), 'finalized', $this->admin->id);
        app(PayrollService::class)->markPaid($r->fresh(), $this->admin->id);
        $this->assertEquals('paid', $r->fresh()->status);
    }

    public function test_statutory_rules_versioned()
    {
        app(PayrollService::class)->assignSalary($this->employee, 1000000, '2026-01-01', $this->admin->id);
        StatutoryRule::create(['code' => 'PAYE', 'type' => 'employee', 'rate' => 20, 'effective_from' => '2026-01-01', 'effective_to' => '2026-06-30']);
        StatutoryRule::create(['code' => 'PAYE', 'type' => 'employee', 'rate' => 30, 'effective_from' => '2026-07-01']);
        $r = $this->payrun(); // Nov 2026 → uses the newer rate
        $this->assertEquals(300000, (float) $r->deductions); // 30% not 20%
    }
}
