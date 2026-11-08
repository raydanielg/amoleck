<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\PatientCharge;
use Modules\Finance\Models\ServicePrice;
use Modules\Finance\Services\FinanceService;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Branch $hq; private Branch $b2;
    private Patient $patient; private ClinicalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        $this->b2 = Branch::create(['name' => 'B2', 'code' => 'B2']);
        \Nwidart\Modules\Facades\Module::find('Finance')?->enable();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['finance', 'hospitaloperations'])->update(['status' => 'active']);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'F', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
        $this->service = ClinicalService::create(['code' => 'IPA', 'name' => 'Initial Assessment', 'status' => 'active']);
    }

    private function approvedPrice(int $price, ?int $branchId = null): ServicePrice
    {
        return ServicePrice::create(['clinical_service_id' => $this->service->id, 'service_name' => $this->service->name, 'price' => $price, 'branch_id' => $branchId, 'status' => 'approved', 'created_by' => $this->admin->id, 'approved_by' => $this->admin->id]);
    }

    private function charge(int $srcId): PatientCharge
    {
        return app(FinanceService::class)->createCharge(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_service_id' => $this->service->id, 'source' => 'visit', 'source_id' => $srcId, 'service_date' => now()], $this->admin->id);
    }

    public function test_price_resolution_branch_then_global()
    {
        $global = $this->approvedPrice(5000);
        $branch = $this->approvedPrice(7000, $this->hq->id);
        $svc = app(FinanceService::class);
        $this->assertEquals($branch->id, $svc->resolvePrice($this->service->id, $this->hq->id)->id);
        $this->assertEquals($global->id, $svc->resolvePrice($this->service->id, $this->b2->id)->id);
    }

    public function test_missing_price_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->charge(1);
    }

    public function test_charge_creation_and_calculation()
    {
        $this->approvedPrice(5000, $this->hq->id);
        $c = app(FinanceService::class)->createCharge(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_service_id' => $this->service->id, 'source' => 'visit', 'source_id' => 1, 'service_date' => now(), 'quantity' => 2, 'discount' => 500], $this->admin->id);
        $this->assertMatchesRegularExpression('/^CHG-\d{4}-\d{6}$/', $c->charge_number);
        $this->assertEquals('9500.00', $c->total);
        $this->assertEquals('5000.00', $c->unit_price);
    }

    public function test_duplicate_source_charge_rejected()
    {
        $this->approvedPrice(5000, $this->hq->id);
        $this->charge(1);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->charge(1);
    }

    public function test_charge_workflow_and_posting()
    {
        $this->approvedPrice(5000, $this->hq->id);
        $c = $this->charge(2);
        $svc = app(FinanceService::class);
        $svc->transitionCharge($c, 'pending_review', $this->admin->id);
        $svc->transitionCharge($c, 'approved', $this->admin->id);
        $svc->transitionCharge($c, 'posted', $this->admin->id);
        $this->assertEquals('posted', $c->fresh()->status);
        $this->assertNotNull($c->fresh()->posted_at);
    }

    public function test_reversal_preserves_original()
    {
        $this->approvedPrice(5000, $this->hq->id);
        $c = $this->charge(3);
        $c->update(['status' => 'posted']);
        app(FinanceService::class)->reverseCharge($c->fresh(), 'Patient dispute', $this->admin->id);
        $c = $c->fresh();
        $this->assertEquals('reversed', $c->status);
        $this->assertEquals('5000.00', $c->total);
        $this->assertEquals('reversal', $c->adjustments()->first()->type);
    }

    public function test_self_price_approval_rejected()
    {
        $p = ServicePrice::create(['clinical_service_id' => $this->service->id, 'service_name' => 'X', 'price' => 1000, 'status' => 'draft', 'created_by' => $this->admin->id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(FinanceService::class)->transitionPrice($p, 'approved', $this->admin->id);
    }

    public function test_historical_charge_immune_to_price_change()
    {
        $this->approvedPrice(5000, $this->hq->id);
        $c = $this->charge(4);
        $this->approvedPrice(8000, $this->hq->id);
        $this->assertEquals('5000.00', $c->fresh()->unit_price);
        $this->assertEquals('5000.00', $c->fresh()->total);
    }

    public function test_negative_total_rejected()
    {
        $this->approvedPrice(1000, $this->hq->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(FinanceService::class)->createCharge(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_service_id' => $this->service->id, 'source' => 'manual', 'source_id' => 5, 'service_date' => now(), 'discount' => 2000], $this->admin->id);
    }

    public function test_audit_logged()
    {
        $this->approvedPrice(5000, $this->hq->id);
        $this->charge(6);
        $this->assertEquals('finance.charge_created', AuditLog::latest('id')->first()->action);
    }
}
