<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Clinical\Models\ClinicalDischarge;
use Modules\PatientCare\Clinical\Models\ClinicalOutcomeMeasure;
use Modules\PatientCare\Clinical\Models\ClinicalOutcomeRecord;
use Modules\PatientCare\Clinical\Models\ClinicalReassessment;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan;
use Modules\PatientCare\Clinical\Services\OutcomeDischargeService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class OutcomeDischargeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $physio; private Branch $hq; private Patient $patient; private ClinicalTreatmentPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
        $this->physio = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->physio->assignRole('Physiotherapist');
        $this->physio->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'O', 'last_name' => 'D', 'primary_branch_id' => $this->hq->id]);
        $this->plan = ClinicalTreatmentPlan::create(['plan_number' => 'TPL-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'status' => 'active', 'clinical_indication' => 'X', 'created_by' => $this->physio->id]);
    }

    public function test_measure_and_record()
    {
        $m = ClinicalOutcomeMeasure::create(['code' => 'OM-0001', 'name' => 'NPRS', 'category' => 'pain', 'supported_units' => ['score']]);
        $this->actingAs($this->physio)->post(route('admin.patients.outcome-records.store', $this->patient), [
            'clinical_outcome_measure_id' => $m->id, 'value_numeric' => 7, 'unit' => 'score', 'status' => 'assessed', 'assessed_at' => now()->toDateTimeString(),
        ]);
        $this->assertEquals(1, ClinicalOutcomeRecord::count());
        // invalid unit rejected
        $this->actingAs($this->physio)->post(route('admin.patients.outcome-records.store', $this->patient), [
            'clinical_outcome_measure_id' => $m->id, 'value_numeric' => 5, 'unit' => 'kg', 'status' => 'assessed', 'assessed_at' => now()->toDateTimeString(),
        ]);
        $this->assertEquals(1, ClinicalOutcomeRecord::count());
    }

    public function test_not_assessed_requires_reason()
    {
        $m = ClinicalOutcomeMeasure::create(['code' => 'OM-0001', 'name' => 'NPRS', 'category' => 'pain']);
        $this->actingAs($this->physio)->post(route('admin.patients.outcome-records.store', $this->patient), [
            'clinical_outcome_measure_id' => $m->id, 'status' => 'unable', 'assessed_at' => now()->toDateTimeString(),
        ])->assertSessionHasErrors('not_assessed_reason');
    }

    public function test_comparison_only_compatible_units()
    {
        $m = ClinicalOutcomeMeasure::create(['code' => 'OM-0001', 'name' => 'ROM', 'category' => 'joint_movement']);
        ClinicalOutcomeRecord::create(['clinical_outcome_measure_id' => $m->id, 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'value_numeric' => 70, 'unit' => 'deg', 'status' => 'assessed', 'assessed_at' => now()->subWeek(), 'clinician_id' => $this->physio->id]);
        ClinicalOutcomeRecord::create(['clinical_outcome_measure_id' => $m->id, 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'value_numeric' => 100, 'unit' => 'deg', 'status' => 'assessed', 'assessed_at' => now(), 'clinician_id' => $this->physio->id]);
        $c = app(OutcomeDischargeService::class)->compare($m->id, $this->patient->id);
        $this->assertTrue($c['comparable']);
        $this->assertEquals(30, $c['abs_change']);
        $this->assertEquals(42.9, $c['pct_change']);
    }

    public function test_reassessment_lifecycle()
    {
        $goal = $this->plan->goals()->create(['description' => 'Flex 110', 'status' => 'in_progress']);
        $this->actingAs($this->physio)->post(route('admin.patients.reassessments.store', $this->patient), ['clinical_treatment_plan_id' => $this->plan->id, 'branch_id' => $this->hq->id]);
        $r = ClinicalReassessment::latest('id')->first();
        $this->assertMatchesRegularExpression('/^REA-\d{4}-\d{6}$/', $r->reassessment_number);
        $this->actingAs($this->physio)->put(route('admin.reassessments.update', $r), ['reassessment' => ['subjective' => 'Better', 'recommendation' => 'discharge'], 'goal_reviews' => [['clinical_treatment_goal_id' => $goal->id, 'status' => 'achieved']]]);
        $this->assertEquals('achieved', $goal->fresh()->status);
        $this->actingAs($this->physio)->post(route('admin.reassessments.transition', $r), ['status' => 'pending_review']);
        $this->actingAs($this->physio)->post(route('admin.reassessments.transition', $r), ['status' => 'finalized']);
        $this->assertEquals('finalized', $r->fresh()->status);
        $this->assertEquals(1, $r->reVersions()->count());
    }

    public function test_discharge_workflow()
    {
        $this->actingAs($this->physio)->post(route('admin.patients.discharges.store', $this->patient), ['branch_id' => $this->hq->id, 'type' => 'goals_achieved']);
        $d = ClinicalDischarge::latest('id')->first();
        $this->assertMatchesRegularExpression('/^DIS-\d{4}-\d{6}$/', $d->discharge_number);
        $this->actingAs($this->physio)->put(route('admin.discharges.update', $d), ['baseline_pain' => 7, 'final_pain' => 2, 'initial_status' => 'Limited', 'final_status' => 'Independent']);
        $this->actingAs($this->physio)->post(route('admin.discharges.transition', $d), ['status' => 'pending_review']);
        $this->actingAs($this->admin)->post(route('admin.discharges.transition', $d), ['status' => 'approved']);
        // finalize requires date
        $this->actingAs($this->admin)->post(route('admin.discharges.transition', $d), ['status' => 'finalized']);
        $this->assertEquals('approved', $d->fresh()->status);
        $d->update(['discharge_on' => now()->toDateString()]);
        $this->actingAs($this->admin)->post(route('admin.discharges.transition', $d), ['status' => 'finalized']);
        $this->assertEquals('finalized', $d->fresh()->status);
        $this->assertEquals(1, $d->versions()->count());
    }

    public function test_finalized_discharge_immutable()
    {
        $d = ClinicalDischarge::create(['discharge_number' => 'DIS-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'goals_achieved', 'status' => 'finalized', 'discharge_on' => now(), 'created_by' => $this->physio->id]);
        $this->actingAs($this->admin)->put(route('admin.discharges.update', $d), ['final_status' => 'changed']);
        $this->assertNull($d->fresh()->final_status);
    }

    public function test_cross_branch_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $foreign = Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $other->id]);
        $d = ClinicalDischarge::create(['discharge_number' => 'DIS-2026-000090', 'patient_id' => $foreign->id, 'branch_id' => $other->id, 'type' => 'goals_achieved', 'status' => 'draft', 'created_by' => $this->admin->id]);
        $this->actingAs($this->physio)->get(route('admin.discharges.show', $d))->assertForbidden();
    }

    public function test_print_discharge()
    {
        $d = ClinicalDischarge::create(['discharge_number' => 'DIS-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'goals_achieved', 'status' => 'finalized', 'discharge_on' => now(), 'created_by' => $this->physio->id]);
        $this->actingAs($this->admin)->get(route('admin.discharges.print', $d))->assertOk();
    }
}
