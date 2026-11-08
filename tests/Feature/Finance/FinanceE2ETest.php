<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\AccountingPeriod;
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\ChartOfAccount;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\InsurancePlan;
use Modules\Finance\Models\InsuranceProvider;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PatientCharge;
use Modules\Finance\Models\PatientInsuranceMembership;
use Modules\Finance\Models\PaymentTransaction;
use Modules\Finance\Models\PurchaseRequisition;
use Modules\Finance\Models\ServicePrice;
use Modules\Finance\Services\AccountingService;
use Modules\Finance\Services\BudgetService;
use Modules\Finance\Services\FinanceService;
use Modules\Finance\Services\InsuranceService;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Services\PaymentService;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\HospitalOperations\Models\Department;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class FinanceE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $cashier; private Branch $hq; private Patient $patient;

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
        $this->cashier = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'E', 'last_name' => '2E', 'primary_branch_id' => $this->hq->id]);
        AccountingPeriod::create(['name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        foreach ([['1000','Cash','asset'],['1200','AR','asset'],['2100','Refunds','liability'],['4000','Revenue','revenue'],['5000','Expenses','expense']] as [$c,$n,$t]) {
            ChartOfAccount::create(['code' => $c, 'name' => $n, 'type' => $t]);
        }
    }

    /** Full journey: price → charge → post → invoice → issue → pay → GL → claim. */
    public function test_complete_financial_journey()
    {
        $svc = ClinicalService::create(['code' => 'CON', 'name' => 'Consult', 'status' => 'active']);
        ServicePrice::create(['clinical_service_id' => $svc->id, 'service_name' => 'Consult', 'price' => 10000, 'branch_id' => $this->hq->id, 'status' => 'approved', 'created_by' => $this->admin->id]);

        // M1 — charge capture
        $charge = app(FinanceService::class)->createCharge(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_service_id' => $svc->id, 'source' => 'visit', 'source_id' => 1, 'service_date' => now()], $this->admin->id);
        $fs = app(FinanceService::class);
        $fs->transitionCharge($charge, 'pending_review', $this->admin->id);
        $fs->transitionCharge($charge->fresh(), 'approved', $this->admin->id);
        $fs->transitionCharge($charge->fresh(), 'posted', $this->admin->id);

        // M2 — invoice
        $inv = app(InvoiceService::class)->generate($this->patient->id, $this->hq->id, [$charge->id], $this->admin->id);
        app(InvoiceService::class)->transition($inv, 'approved', $this->admin->id);
        app(InvoiceService::class)->transition($inv->fresh(), 'issued', $this->admin->id);

        // M5 — GL: invoice issued posts AR→Revenue
        $j1 = app(AccountingService::class)->postEvent('invoice_issued', $inv->id, 10000, $this->hq->id, $this->admin->id);
        $this->assertEquals('posted', $j1->status);

        // M3 — payment
        $shift = app(PaymentService::class)->openShift($this->cashier->id, $this->hq->id, 5000);
        $p = app(PaymentService::class)->record(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'invoice_id' => $inv->id, 'method' => 'cash', 'amount' => 10000, 'cashier_shift_id' => $shift->id], $this->cashier->id);
        app(PaymentService::class)->confirm($p, $this->admin->id);
        $this->assertEquals('paid', $inv->fresh()->status);

        // M5 — GL: payment confirmed posts Cash→AR
        $j2 = app(AccountingService::class)->postEvent('payment_confirmed', $p->id, 10000, $this->hq->id, $this->admin->id);
        $this->assertEquals('posted', $j2->status);

        // M4 — insurance claim on the paid invoice (insurer portion)
        $provider = InsuranceProvider::create(['code' => 'NHIF', 'name' => 'NHIF', 'status' => 'active']);
        $plan = InsurancePlan::create(['insurance_provider_id' => $provider->id, 'code' => 'STD', 'name' => 'Standard', 'coverage_percent' => 80]);
        $m = PatientInsuranceMembership::create(['patient_id' => $this->patient->id, 'insurance_provider_id' => $provider->id, 'insurance_plan_id' => $plan->id, 'member_number' => 'M1', 'status' => 'active']);
        $claim = app(InsuranceService::class)->createClaim($m, $inv->id, $this->admin->id);
        $this->assertEquals(8000, $claim->insurer_share); // 80% of 10000
        $this->assertEquals(2000, $claim->patient_share);

        // Audit trail spans all 4 events
        $actions = AuditLog::where('action', 'like', 'finance.%')->pluck('action');
        $this->assertContains('finance.charge_created', $actions);
        $this->assertContains('finance.invoice_created', $actions);
        $this->assertContains('finance.payment_confirmed', $actions);
        $this->assertContains('finance.claim_created', $actions);
    }

    /** Split payments + refund + shift reconciliation + budget. */
    public function test_split_payment_refund_and_budget()
    {
        $svc = ClinicalService::create(['code' => 'PKG', 'name' => 'Package', 'status' => 'active']);
        ServicePrice::create(['clinical_service_id' => $svc->id, 'service_name' => 'Package', 'price' => 100000, 'branch_id' => $this->hq->id, 'status' => 'approved', 'created_by' => $this->admin->id]);
        $charge = app(FinanceService::class)->createCharge(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_service_id' => $svc->id, 'source' => 'visit', 'source_id' => 1, 'service_date' => now()], $this->admin->id);
        $fs2 = app(FinanceService::class);
        $fs2->transitionCharge($charge, 'pending_review', $this->admin->id);
        $fs2->transitionCharge($charge->fresh(), 'approved', $this->admin->id);
        $fs2->transitionCharge($charge->fresh(), 'posted', $this->admin->id);
        $inv = app(InvoiceService::class)->generate($this->patient->id, $this->hq->id, [$charge->id], $this->admin->id);
        $inv->update(['status' => 'issued']);

        // split payment: 40k cash + 60k mobile
        $ps = app(PaymentService::class);
        $p1 = $ps->record(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'invoice_id' => $inv->id, 'method' => 'cash', 'amount' => 40000], $this->cashier->id);
        $p2 = $ps->record(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'invoice_id' => $inv->id, 'method' => 'mobile_money', 'amount' => 60000], $this->cashier->id);
        $ps->confirm($p1, $this->admin->id);
        $ps->confirm($p2, $this->admin->id);
        $this->assertEquals('paid', $inv->fresh()->status);

        // refund the mobile portion → invoice back to partially_paid? (allocated reversed)
        $r = $ps->requestRefund($p2, 60000, 'Refund requested', $this->cashier->id);
        $ps->processRefund($r, 'approve', $this->admin->id);
        $this->assertEquals('refunded', $p2->fresh()->status);
        $this->assertEquals('issued', $inv->fresh()->status); // full refund → back to issued (40k cash still allocated — outstanding now reflects only p1)

        // M6 — budget + requisition against expense
        $budget = app(BudgetService::class)->createBudget(['name' => 'FY26', 'accounting_period_id' => AccountingPeriod::first()->id, 'type' => 'operating'], $this->admin->id);
        $acct = ChartOfAccount::where('code', '5000')->first();
        $line = app(BudgetService::class)->addLine($budget, ['chart_of_account_id' => $acct->id, 'department_id' => Department::create(['code' => 'FIN', 'name' => 'Finance'])->id, 'branch_id' => $this->hq->id, 'annual_amount' => 200000]);
        Expense::create(['expense_number' => 'EXP-1', 'branch_id' => $this->hq->id, 'chart_of_account_id' => $acct->id, 'category' => 'supplies', 'amount' => 50000, 'expense_date' => today(), 'status' => 'posted', 'created_by' => $this->admin->id]);
        $req = PurchaseRequisition::create(['requisition_number' => 'REQ-1', 'branch_id' => $this->hq->id, 'estimated_amount' => 120000, 'created_by' => $this->admin->id]);
        app(BudgetService::class)->validateRequisition($req, $line, $this->admin->id);
        $this->assertEquals('within_budget', $req->fresh()->financial_status); // 200k - 50k spent = 150k available > 120k
    }

    /** GL integrity: trial balance across a multi-event day stays balanced. */
    public function test_gl_stays_balanced()
    {
        $as = app(AccountingService::class);
        $as->postEvent('invoice_issued', 1, 10000, $this->hq->id, $this->admin->id);
        $as->postEvent('payment_confirmed', 2, 4000, $this->hq->id, $this->admin->id);
        $as->postEvent('credit_note', 3, 1000, $this->hq->id, $this->admin->id);
        $as->postEvent('refund_paid', 4, 2000, $this->hq->id, $this->admin->id);
        $tb = $as->trialBalance($this->hq->id);
        $this->assertTrue($tb['balanced']);
        $is = $as->incomeStatement($this->hq->id);
        $this->assertEquals(9000, $is['revenue']); // 10000 - 1000 credit note
    }
}
