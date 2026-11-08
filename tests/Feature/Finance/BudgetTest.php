<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\AccountingPeriod;
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\BudgetLine;
use Modules\Finance\Models\ChartOfAccount;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\PurchaseRequisition;
use Modules\Finance\Services\BudgetService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Department;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $approver; private Budget $budget; private BudgetLine $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Finance')?->enable();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['finance', 'hospitaloperations'])->update(['status' => 'active']);
        AccountingPeriod::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        $acct = ChartOfAccount::create(['code' => '5000', 'name' => 'Expenses', 'type' => 'expense']);
        $dept = Department::create(['code' => 'PHY', 'name' => 'Physiotherapy']);
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->budget = app(BudgetService::class)->createBudget(['name' => 'FY2026', 'accounting_period_id' => AccountingPeriod::first()->id, 'type' => 'operating'], $this->admin->id);
        $this->line = app(BudgetService::class)->addLine($this->budget, ['chart_of_account_id' => $acct->id, 'department_id' => $dept->id, 'branch_id' => $this->hq->id, 'annual_amount' => 100000]);
    }

    public function test_budget_creation_and_numbering()
    {
        $this->assertMatchesRegularExpression('/^BDG-\d{4}-\d{6}$/', $this->budget->reference);
        $this->assertEquals('draft', $this->budget->status);
        $this->assertEquals(100000, $this->budget->fresh()->total);
    }

    public function test_approval_workflow()
    {
        $svc = app(BudgetService::class);
        $svc->transition($this->budget, 'submitted', $this->admin->id);
        $svc->transition($this->budget->fresh(), 'under_review', $this->admin->id);
        $svc->transition($this->budget->fresh(), 'approved', $this->approver->id);
        $this->assertEquals('approved', $this->budget->fresh()->status);
    }

    public function test_self_approval_rejected()
    {
        $svc = app(BudgetService::class);
        $svc->transition($this->budget, 'submitted', $this->admin->id);
        $svc->transition($this->budget->fresh(), 'under_review', $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transition($this->budget->fresh(), 'approved', $this->admin->id);
    }

    public function test_utilization_with_expense()
    {
        Expense::create(['expense_number' => 'EXP-1', 'branch_id' => $this->hq->id, 'chart_of_account_id' => $this->line->chart_of_account_id, 'category' => 'supplies', 'amount' => 30000, 'expense_date' => today(), 'status' => 'posted', 'created_by' => $this->admin->id]);
        $u = app(BudgetService::class)->utilization($this->line);
        $this->assertEquals(100000, $u['budget']);
        $this->assertEquals(30000, $u['spent']);
        $this->assertEquals(70000, $u['available']);
        $this->assertEquals(30, $u['utilization']);
    }

    public function test_draft_expenses_not_counted()
    {
        Expense::create(['expense_number' => 'EXP-1', 'branch_id' => $this->hq->id, 'chart_of_account_id' => $this->line->chart_of_account_id, 'category' => 'supplies', 'amount' => 30000, 'expense_date' => today(), 'status' => 'draft', 'created_by' => $this->admin->id]);
        $this->assertEquals(0, app(BudgetService::class)->utilization($this->line)['spent']);
    }

    public function test_requisition_within_budget()
    {
        $r = PurchaseRequisition::create(['requisition_number' => 'REQ-1', 'branch_id' => $this->hq->id, 'estimated_amount' => 50000, 'created_by' => $this->admin->id]);
        app(BudgetService::class)->validateRequisition($r, $this->line, $this->admin->id);
        $this->assertEquals('within_budget', $r->fresh()->financial_status);
    }

    public function test_requisition_insufficient_budget()
    {
        $r = PurchaseRequisition::create(['requisition_number' => 'REQ-1', 'branch_id' => $this->hq->id, 'estimated_amount' => 150000, 'created_by' => $this->admin->id]);
        app(BudgetService::class)->validateRequisition($r, $this->line, $this->admin->id);
        $this->assertEquals('insufficient_budget', $r->fresh()->financial_status);
    }

    public function test_approved_requisition_creates_commitment()
    {
        $svc = app(BudgetService::class);
        $r = PurchaseRequisition::create(['requisition_number' => 'REQ-1', 'branch_id' => $this->hq->id, 'estimated_amount' => 50000, 'created_by' => $this->admin->id]);
        $svc->validateRequisition($r, $this->line, $this->admin->id);
        $c = $svc->approveRequisition($r->fresh(), $this->admin->id);
        $this->assertMatchesRegularExpression('/^COM-\d{4}-\d{6}$/', $c->reference);
        $this->assertEquals(50000, $c->amount);
        $this->assertEquals(50000, $svc->available($this->line)); // 100k - 50k committed
    }

    public function test_commitment_release_frees_budget()
    {
        $svc = app(BudgetService::class);
        $r = PurchaseRequisition::create(['requisition_number' => 'REQ-1', 'branch_id' => $this->hq->id, 'estimated_amount' => 50000, 'created_by' => $this->admin->id]);
        $svc->validateRequisition($r, $this->line, $this->admin->id);
        $c = $svc->approveRequisition($r->fresh(), $this->admin->id);
        $svc->releaseCommitment($c, $this->admin->id);
        $this->assertEquals(100000, $svc->available($this->line));
    }

    public function test_insufficient_requisition_cannot_approve()
    {
        $r = PurchaseRequisition::create(['requisition_number' => 'REQ-1', 'branch_id' => $this->hq->id, 'estimated_amount' => 150000, 'created_by' => $this->admin->id]);
        app(BudgetService::class)->validateRequisition($r, $this->line, $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BudgetService::class)->approveRequisition($r->fresh(), $this->admin->id);
    }
}
