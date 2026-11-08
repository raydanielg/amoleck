<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PatientCharge;
use Modules\Finance\Models\ServicePrice;
use Modules\Finance\Services\FinanceService;
use Modules\Finance\Services\InvoiceService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Branch $hq; private Patient $patient; private PatientCharge $charge;

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
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'B', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
        $service = ClinicalService::create(['code' => 'CON', 'name' => 'Consult', 'status' => 'active']);
        ServicePrice::create(['clinical_service_id' => $service->id, 'service_name' => 'Consult', 'price' => 5000, 'branch_id' => $this->hq->id, 'status' => 'approved', 'created_by' => $this->admin->id]);
        $this->charge = app(FinanceService::class)->createCharge(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_service_id' => $service->id, 'source' => 'visit', 'source_id' => 1, 'service_date' => now()], $this->admin->id);
    }

    private function invoice(): Invoice
    {
        $this->charge->update(['status' => 'posted']);
        return app(InvoiceService::class)->generate($this->patient->id, $this->hq->id, [$this->charge->id], $this->admin->id);
    }

    public function test_invoice_generated_from_posted_charge()
    {
        $inv = $this->invoice();
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $inv->invoice_number);
        $this->assertEquals('5000.00', $inv->total);
        $this->assertEquals(1, $inv->items()->count());
        $this->assertEquals($this->charge->id, $inv->items()->first()->patient_charge_id);
    }

    public function test_unposted_charge_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InvoiceService::class)->generate($this->patient->id, $this->hq->id, [$this->charge->id], $this->admin->id); // still draft
    }

    public function test_double_allocation_blocked()
    {
        $this->invoice();
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        app(InvoiceService::class)->generate($this->patient->id, $this->hq->id, [$this->charge->id], $this->admin->id);
    }

    public function test_invoice_workflow()
    {
        $inv = $this->invoice();
        $svc = app(InvoiceService::class);
        $svc->transition($inv, 'pending_review', $this->admin->id);
        $svc->transition($inv, 'approved', $this->admin->id);
        $svc->transition($inv, 'issued', $this->admin->id);
        $this->assertEquals('issued', $inv->fresh()->status);
        $this->assertNotNull($inv->fresh()->due_date);
    }

    public function test_credit_note_reduces_outstanding()
    {
        $inv = $this->invoice();
        $inv->update(['status' => 'issued']);
        $svc = app(InvoiceService::class);
        $svc->issueCreditNote($inv->fresh(), 2000, 'Pricing error', 'partial', $this->admin->id);
        $this->assertEquals('partially_credited', $inv->fresh()->status);
        $this->assertEquals(3000, $svc->outstanding($inv->fresh()));
    }

    public function test_full_credit_marks_credited()
    {
        $inv = $this->invoice();
        $inv->update(['status' => 'issued']);
        $svc = app(InvoiceService::class);
        $svc->issueCreditNote($inv->fresh(), 5000, 'Full refund', 'full', $this->admin->id);
        $this->assertEquals('credited', $inv->fresh()->status);
        $this->assertEquals(0, $svc->outstanding($inv->fresh()));
    }

    public function test_credit_exceeding_outstanding_rejected()
    {
        $inv = $this->invoice();
        $inv->update(['status' => 'issued']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InvoiceService::class)->issueCreditNote($inv->fresh(), 9999, 'Too much', 'partial', $this->admin->id);
    }

    public function test_issued_invoice_cannot_be_cancelled_directly()
    {
        $inv = $this->invoice();
        $inv->update(['status' => 'issued']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InvoiceService::class)->transition($inv->fresh(), 'cancelled', $this->admin->id, 'x');
    }

    public function test_aging_buckets()
    {
        $inv = $this->invoice();
        $inv->update(['status' => 'issued', 'due_date' => now()->subDays(45)]);
        $this->assertEquals('31-60', app(InvoiceService::class)->agingBucket($inv));
        $inv->update(['due_date' => now()->subDays(95)]);
        $this->assertEquals('90+', app(InvoiceService::class)->agingBucket($inv->fresh()));
    }

    public function test_statement_totals()
    {
        $inv = $this->invoice();
        $inv->update(['status' => 'issued']);
        app(InvoiceService::class)->issueCreditNote($inv->fresh(), 1500, 'Adj', 'partial', $this->admin->id);
        $st = app(InvoiceService::class)->statement($this->patient->id, $this->hq->id);
        $this->assertEquals(5000, $st['total']);
        $this->assertEquals(1500, $st['credited']);
        $this->assertEquals(3500, $st['outstanding']);
    }

    public function test_cross_branch_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$other->id => ['is_primary' => true]]);
        $inv = $this->invoice();
        $this->actingAs($staff)->get(route('admin.billing.show', $inv))->assertForbidden();
    }
}
