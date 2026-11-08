<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PaymentTransaction;
use Modules\Finance\Models\CashierShift;
use Modules\Finance\Services\FinanceService;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Services\PaymentService;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $cashier; private Branch $hq; private Patient $patient; private Invoice $inv;

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
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'P', 'last_name' => 'M', 'primary_branch_id' => $this->hq->id]);
        $svc = ClinicalService::create(['code' => 'CON', 'name' => 'Consult', 'status' => 'active']);
        \Modules\Finance\Models\ServicePrice::create(['clinical_service_id' => $svc->id, 'service_name' => 'Consult', 'price' => 10000, 'branch_id' => $this->hq->id, 'status' => 'approved', 'created_by' => $this->admin->id]);
        $charge = app(FinanceService::class)->createCharge(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_service_id' => $svc->id, 'source' => 'visit', 'source_id' => 1, 'service_date' => now()], $this->admin->id);
        $charge->update(['status' => 'posted']);
        $this->inv = app(InvoiceService::class)->generate($this->patient->id, $this->hq->id, [$charge->id], $this->admin->id);
        $this->inv->update(['status' => 'issued', 'issue_date' => today()]);
    }

    private function pay(float $amount, string $method = 'cash'): PaymentTransaction
    {
        return app(PaymentService::class)->record(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'invoice_id' => $this->inv->id, 'method' => $method, 'amount' => $amount], $this->cashier->id);
    }

    public function test_payment_recorded_then_confirmed()
    {
        $p = $this->pay(10000);
        $this->assertMatchesRegularExpression('/^PAY-\d{4}-\d{6}$/', $p->payment_number);
        $this->assertEquals('initiated', $p->status);
        app(PaymentService::class)->confirm($p, $this->admin->id);
        $p = $p->fresh();
        $this->assertEquals('successful', $p->status);
        $this->assertNotNull($p->confirmed_at);
        $this->assertEquals('paid', $this->inv->fresh()->status); // full payment
    }

    public function test_partial_payments_accumulate()
    {
        $svc = app(PaymentService::class);
        $svc->confirm($this->pay(4000), $this->admin->id);
        $this->assertEquals('partially_paid', $this->inv->fresh()->status);
        $svc->confirm($this->pay(6000), $this->admin->id);
        $this->assertEquals('paid', $this->inv->fresh()->status);
        $this->assertEquals(2, $this->inv->paymentAllocations()->count());
    }

    public function test_overpayment_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->pay(15000);
    }

    public function test_unconfirmed_does_not_reduce_balance()
    {
        $this->pay(10000); // recorded but not confirmed
        $this->assertEquals('issued', $this->inv->fresh()->status);
        $this->assertEquals(0, $this->inv->paymentAllocations()->count());
    }

    public function test_split_payment_methods()
    {
        $svc = app(PaymentService::class);
        $svc->confirm($this->pay(4000, 'cash'), $this->admin->id);
        $svc->confirm($this->pay(3000, 'mobile_money'), $this->admin->id);
        $svc->confirm($this->pay(3000, 'bank'), $this->admin->id);
        $this->assertEquals('paid', $this->inv->fresh()->status);
        $this->assertEquals(3, PaymentTransaction::where('invoice_id', $this->inv->id)->where('status', 'successful')->count());
    }

    public function test_refund_capped_at_paid()
    {
        $svc = app(PaymentService::class);
        $p = $this->pay(10000);
        $svc->confirm($p, $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->requestRefund($p, 15000, 'Too much', $this->cashier->id);
    }

    public function test_refund_workflow()
    {
        $svc = app(PaymentService::class);
        $p = $this->pay(10000);
        $svc->confirm($p, $this->admin->id);
        $r = $svc->requestRefund($p, 10000, 'Duplicate payment', $this->cashier->id);
        $svc->processRefund($r, 'approve', $this->admin->id);
        $this->assertEquals('successful', $r->fresh()->status);
        $this->assertEquals('refunded', $p->fresh()->status);
        $this->assertEquals('issued', $this->inv->fresh()->status); // back to unpaid
    }

    public function test_cashier_shift_variance()
    {
        $svc = app(PaymentService::class);
        $shift = $svc->openShift($this->cashier->id, $this->hq->id, 5000);
        $p = $this->pay(10000);
        $p->update(['cashier_shift_id' => $shift->id]);
        $svc->confirm($p, $this->admin->id);
        $svc->closeShift($shift->fresh(), 15500, $this->admin->id); // 5000 opening + 10000 received = 15000 expected, actual 15500
        $s = $shift->fresh();
        $this->assertEquals(15000, $s->expected_closing);
        $this->assertEquals(500, $s->variance);
        $this->assertEquals('closed', $s->status);
    }

    public function test_unpaid_invoice_not_payable()
    {
        $this->inv->update(['status' => 'cancelled']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->pay(5000);
    }

    public function test_cross_branch_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$other->id => ['is_primary' => true]]);
        $this->actingAs($staff)->post(route('admin.payments.transactions.store'), ['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'invoice_id' => $this->inv->id, 'method' => 'cash', 'amount' => 5000])->assertForbidden();
    }

    public function test_audit_logged()
    {
        $p = $this->pay(5000);
        $this->assertEquals('finance.payment_recorded', AuditLog::latest('id')->first()->action);
    }
}
