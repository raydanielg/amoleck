<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\InsuranceClaim;
use Modules\Finance\Models\InsurancePlan;
use Modules\Finance\Models\InsurancePreauthorization;
use Modules\Finance\Models\InsuranceProvider;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PatientInsuranceMembership;
use Modules\Finance\Services\InsuranceService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class InsuranceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Patient $patient; private PatientInsuranceMembership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Finance')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'finance')->update(['status' => 'active']);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'I', 'last_name' => 'P', 'primary_branch_id' => $hq->id]);
        $provider = InsuranceProvider::create(['code' => 'NHIF', 'name' => 'NHIF', 'status' => 'active']);
        $plan = InsurancePlan::create(['insurance_provider_id' => $provider->id, 'code' => 'STD', 'name' => 'Standard', 'coverage_percent' => 80, 'per_visit_limit' => 50000, 'requires_preauth' => true]);
        $this->membership = PatientInsuranceMembership::create(['patient_id' => $this->patient->id, 'insurance_provider_id' => $provider->id, 'insurance_plan_id' => $plan->id, 'member_number' => 'M123', 'status' => 'pending']);
    }

    public function test_membership_verification()
    {
        $svc = app(InsuranceService::class);
        $svc->verifyMembership($this->membership, $this->admin->id);
        $this->assertEquals('active', $this->membership->fresh()->status);
    }

    public function test_expired_membership_cannot_verify_active()
    {
        $this->membership->update(['effective_to' => now()->subYear()]);
        app(InsuranceService::class)->verifyMembership($this->membership->fresh(), $this->admin->id);
        $this->assertEquals('expired', $this->membership->fresh()->status);
    }

    public function test_coverage_split_calculation()
    {
        $this->membership->update(['status' => 'active']);
        $split = app(InsuranceService::class)->coverageSplit($this->membership->fresh(), 100000);
        $this->assertEquals(50000, $split['insurer']); // capped at per_visit_limit
        $this->assertEquals(50000, $split['patient']);
    }

    public function test_coverage_respects_percent()
    {
        $this->membership->update(['status' => 'active']);
        $split = app(InsuranceService::class)->coverageSplit($this->membership->fresh(), 30000);
        $this->assertEquals(24000, $split['insurer']); // 80% of 30000
        $this->assertEquals(6000, $split['patient']);
    }

    public function test_inactive_membership_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InsuranceService::class)->coverageSplit($this->membership, 10000); // still pending
    }

    public function test_claim_created_and_unique_per_invoice()
    {
        $this->membership->update(['status' => 'active']);
        $inv = Invoice::create(['invoice_number' => 'INV-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->patient->primary_branch_id, 'total' => 100000, 'status' => 'issued', 'created_by' => $this->admin->id]);
        $svc = app(InsuranceService::class);
        $c = $svc->createClaim($this->membership->fresh(), $inv->id, $this->admin->id);
        $this->assertMatchesRegularExpression('/^CLM-\d{4}-\d{6}$/', $c->claim_number);
        $this->assertEquals(50000, $c->insurer_share);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $svc->createClaim($this->membership->fresh(), $inv->id, $this->admin->id);
    }

    public function test_claim_workflow()
    {
        $this->membership->update(['status' => 'active']);
        $inv = Invoice::create(['invoice_number' => 'INV-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->patient->primary_branch_id, 'total' => 100000, 'status' => 'issued', 'created_by' => $this->admin->id]);
        $c = app(InsuranceService::class)->createClaim($this->membership->fresh(), $inv->id, $this->admin->id);
        $svc = app(InsuranceService::class);
        $svc->transitionClaim($c, 'submitted', $this->admin->id);
        $svc->transitionClaim($c, 'acknowledged', $this->admin->id);
        $svc->transitionClaim($c, 'approved', $this->admin->id);
        $svc->transitionClaim($c, 'paid', $this->admin->id, ['amount' => 45000]);
        $this->assertEquals('paid', $c->fresh()->status);
        $this->assertEquals(45000, $c->fresh()->paid_amount);
    }

    public function test_rejected_claim_needs_reason()
    {
        $this->membership->update(['status' => 'active']);
        $inv = Invoice::create(['invoice_number' => 'INV-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->patient->primary_branch_id, 'total' => 100000, 'status' => 'issued', 'created_by' => $this->admin->id]);
        $c = app(InsuranceService::class)->createClaim($this->membership->fresh(), $inv->id, $this->admin->id);
        $c->update(['status' => 'under_review']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InsuranceService::class)->transitionClaim($c->fresh(), 'rejected', $this->admin->id); // no reason
    }

    public function test_preauth_requires_plan_flag()
    {
        $this->membership->update(['status' => 'active']);
        $plan2 = InsurancePlan::create(['insurance_provider_id' => $this->membership->insurance_provider_id, 'code' => 'NO-PA', 'name' => 'No PA', 'coverage_percent' => 100, 'requires_preauth' => false]);
        $m2 = PatientInsuranceMembership::create(['patient_id' => $this->patient->id, 'insurance_provider_id' => $this->membership->insurance_provider_id, 'insurance_plan_id' => $plan2->id, 'member_number' => 'M456', 'status' => 'active']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InsuranceService::class)->createPreauth(['patient_insurance_membership_id' => $m2->id], $this->admin->id);
    }

    public function test_preauth_workflow()
    {
        $this->membership->update(['status' => 'active']);
        $svc = app(InsuranceService::class);
        $pa = $svc->createPreauth(['patient_insurance_membership_id' => $this->membership->id, 'estimated_cost' => 80000], $this->admin->id);
        $svc->transitionPreauth($pa, 'submitted', $this->admin->id);
        $svc->transitionPreauth($pa->fresh(), 'partially_approved', $this->admin->id, ['amount' => 60000, 'sessions' => 5]);
        $this->assertEquals('partially_approved', $pa->fresh()->status);
        $this->assertEquals(60000, $pa->fresh()->approved_amount);
    }
}
