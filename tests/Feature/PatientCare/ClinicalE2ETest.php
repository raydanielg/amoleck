<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Clinical\Models\ClinicalDischarge;
use Modules\PatientCare\Clinical\Models\ClinicalExercise;
use Modules\PatientCare\Clinical\Models\ClinicalExercisePrescription;
use Modules\PatientCare\Clinical\Models\ClinicalHomeExerciseProgram;
use Modules\PatientCare\Clinical\Models\ClinicalIntervention;
use Modules\PatientCare\Clinical\Models\ClinicalOutcomeMeasure;
use Modules\PatientCare\Clinical\Models\ClinicalOutcomeRecord;
use Modules\PatientCare\Clinical\Models\ClinicalReassessment;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentSession;
use Modules\PatientCare\Clinical\Services\ClinicalEncounterService;
use Modules\PatientCare\Clinical\Services\ExerciseProgramService;
use Modules\PatientCare\Clinical\Services\TreatmentPlanService;
use Modules\PatientCare\Clinical\Services\TreatmentSessionService;
use Modules\PatientCare\Clinical\Services\OutcomeDischargeService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class ClinicalE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $physio; private User $doctor; private User $rec;
    private Branch $hq; private Branch $b2; private Branch $b3;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        $this->b2 = Branch::create(['name' => 'B2', 'code' => 'B2']);
        $this->b3 = Branch::create(['name' => 'B3', 'code' => 'B3']);
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
        $this->physio = $this->staff('Physiotherapist', [$this->hq->id, $this->b2->id]);
        $this->doctor = $this->staff('Doctor', [$this->hq->id]);
        $this->rec = $this->staff('Receptionist', [$this->hq->id]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'J', 'last_name' => 'D', 'primary_branch_id' => $this->hq->id]);
        $this->patient->branches()->sync([$this->hq->id => ['event' => 'registered']]);
    }

    private function staff(string $role, array $branchIds): User
    {
        $u = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $u->assignRole($role);
        $u->branches()->sync(collect($branchIds)->mapWithKeys(fn ($id, $i) => [$id => ['is_primary' => $i === 0]])->all());
        return $u;
    }

    /** Scenario A: register → assess → plan → approve → session → HEP → adherence → reassess → discharge. */
    public function test_scenario_a_complete_physiotherapy_journey()
    {
        $enc = app(ClinicalEncounterService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial', 'encounter_at' => now()], $this->physio->id);
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), [
            'assessment' => ['subjective' => 'Knee pain 3mo', 'objective' => 'ROM 70'],
            'pain' => [['location' => 'L knee', 'nprs_current' => 7, 'nprs_worst' => 9]],
            'problems' => [['description' => 'Reduced flexion', 'body_region' => 'knee']],
            'red_flags' => [['category' => 'suspected_fracture', 'description' => 'Ruled out', 'status' => 'suspected']],
        ]);
        $enc->update(['status' => 'in_progress']); $this->actingAs($this->admin)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized']);
        $this->assertEquals('finalized', $enc->fresh()->status);

        $plan = app(TreatmentPlanService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_encounter_id' => $enc->id, 'clinical_indication' => 'Post-injury rehab'], $this->physio->id);
        $this->actingAs($this->physio)->put(route('admin.treatment-plans.update', $plan), [
            'problems' => [['description' => 'Reduced flexion']],
            'goals' => [['description' => 'Flex 110°', 'type' => 'short_term', 'baseline' => '70', 'target' => '110', 'unit' => 'deg']],
            'planned_interventions' => [['clinical_intervention_id' => ClinicalIntervention::create(['name' => 'ROM ex', 'category' => 'therapeutic_exercise', 'active' => true])->id]],
        ]);
        $goal = $plan->goals()->first();
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $plan), ['status' => 'pending_review']);
        $this->actingAs($this->doctor)->post(route('admin.treatment-plans.transition', $plan), ['status' => 'approved']);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $plan), ['status' => 'active']);
        $this->assertEquals('active', $plan->fresh()->status);

        $s = app(TreatmentSessionService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_treatment_plan_id' => $plan->id, 'type' => 'routine', 'session_at' => now()], $this->physio->id);
        $this->actingAs($this->physio)->put(route('admin.sessions.update', $s), [
            'session' => ['pre_pain' => 7, 'post_pain' => 4, 'overall_tolerance' => 'well_tolerated'],
            'interventions' => [['name' => 'ROM ex', 'status' => 'performed', 'sets' => 3, 'reps' => 10]],
            'notes' => ['subjective' => 'Improving', 'objective' => 'Flex 80', 'assessment' => 'On track', 'plan' => 'Continue'],
        ]);
        $this->actingAs($this->physio)->post(route('admin.sessions.goals.progress', [$s, $goal]), ['value' => '80', 'status' => 'in_progress']);
        $s->update(['status' => 'in_progress']); $this->actingAs($this->admin)->post(route('admin.sessions.transition', $s), ['status' => 'finalized']);

        $rx = ClinicalExercisePrescription::create(['rx_number' => 'RX-2026-000001', 'patient_id' => $this->patient->id, 'clinical_treatment_plan_id' => $plan->id, 'branch_id' => $this->hq->id, 'prescribed_on' => now(), 'physiotherapist_id' => $this->physio->id, 'created_by' => $this->physio->id]);
        $hep = ClinicalHomeExerciseProgram::create(['hep_number' => 'HEP-2026-000001', 'patient_id' => $this->patient->id, 'clinical_treatment_plan_id' => $plan->id, 'clinical_exercise_prescription_id' => $rx->id, 'branch_id' => $this->hq->id, 'issued_on' => now(), 'status' => 'active', 'physiotherapist_id' => $this->physio->id, 'created_by' => $this->physio->id]);
        $hep->exercises()->create(['custom_name' => 'Knee bends', 'sets' => 3, 'reps' => 10, 'frequency' => 'daily']);
        $this->actingAs($this->physio)->post(route('admin.exercise-programs.adherence', $hep), ['performed_on' => now()->toDateString(), 'status' => 'completed', 'source' => 'patient_reported']);

        $m = ClinicalOutcomeMeasure::create(['code' => 'OM-0001', 'name' => 'Knee ROM', 'category' => 'joint_movement', 'supported_units' => ['deg']]);
        ClinicalOutcomeRecord::create(['clinical_outcome_measure_id' => $m->id, 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'value_numeric' => 70, 'unit' => 'deg', 'status' => 'assessed', 'assessed_at' => now()->subMonth(), 'clinician_id' => $this->physio->id]);
        ClinicalOutcomeRecord::create(['clinical_outcome_measure_id' => $m->id, 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'value_numeric' => 100, 'unit' => 'deg', 'status' => 'assessed', 'assessed_at' => now(), 'clinician_id' => $this->physio->id]);
        $this->assertEquals(30, app(OutcomeDischargeService::class)->compare($m->id, $this->patient->id)['abs_change']);

        $r = app(OutcomeDischargeService::class);
        $re = ClinicalReassessment::create(['reassessment_number' => 'REA-2026-000001', 'patient_id' => $this->patient->id, 'clinical_treatment_plan_id' => $plan->id, 'branch_id' => $this->hq->id, 'physiotherapist_id' => $this->physio->id, 'status' => 'draft', 'created_by' => $this->physio->id]);
        $this->actingAs($this->physio)->put(route('admin.reassessments.update', $re), ['reassessment' => ['recommendation' => 'discharge'], 'goal_reviews' => [['clinical_treatment_goal_id' => $goal->id, 'status' => 'achieved']]]);
        $this->assertEquals('achieved', $goal->fresh()->status);
        $this->actingAs($this->physio)->post(route('admin.reassessments.transition', $re), ['status' => 'pending_review']);
        $this->actingAs($this->physio)->post(route('admin.reassessments.transition', $re), ['status' => 'finalized']);

        $d = ClinicalDischarge::create(['discharge_number' => 'DIS-2026-000001', 'patient_id' => $this->patient->id, 'clinical_treatment_plan_id' => $plan->id, 'branch_id' => $this->hq->id, 'type' => 'goals_achieved', 'discharge_on' => now(), 'status' => 'draft', 'physiotherapist_id' => $this->physio->id, 'created_by' => $this->physio->id]);
        $this->actingAs($this->physio)->put(route('admin.discharges.update', $d), ['baseline_pain' => 7, 'final_pain' => 2, 'hep_recommendation' => 'Continue HEP']);
        $this->actingAs($this->physio)->post(route('admin.discharges.transition', $d), ['status' => 'pending_review']);
        $this->actingAs($this->admin)->post(route('admin.discharges.transition', $d), ['status' => 'approved']);
        $this->actingAs($this->admin)->post(route('admin.discharges.transition', $d), ['status' => 'finalized']);
        $this->assertEquals('finalized', $d->fresh()->status);

        // patient retains full clinical history
        $this->patient->refresh();
        $this->assertEquals(1, $this->patient->clinicalEncounters()->count());
        $this->assertEquals(1, $this->patient->treatmentPlans()->count());
        $this->assertEquals(1, $this->patient->clinicalSessions()->count());
        $this->assertEquals(1, $this->patient->homeExercisePrograms()->count());
        $this->assertEquals(2, $this->patient->outcomeRecords()->count());
        $this->assertEquals(1, ClinicalDischarge::where('patient_id', $this->patient->id)->count());
        // audit trail exists across the journey
        $this->assertGreaterThanOrEqual(6, AuditLog::where('entity_type', Patient::class)->where('entity_id', $this->patient->id)->count());
    }

    /** Scenario B: plan amendment preserves sessions. */
    public function test_scenario_b_plan_version_preserves_session_context()
    {
        $plan = app(TreatmentPlanService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_indication' => 'X'], $this->physio->id);
        $plan->update(['status' => 'active']);
        $s = app(TreatmentSessionService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_treatment_plan_id' => $plan->id, 'type' => 'routine', 'session_at' => now()], $this->physio->id);
        $this->actingAs($this->physio)->put(route('admin.sessions.update', $s), ['interventions' => [['name' => 'Old ROM', 'status' => 'performed']]]);
        $s->update(['status' => 'in_progress']); $this->actingAs($this->admin)->post(route('admin.sessions.transition', $s), ['status' => 'finalized']);
        // plan changes later
        $this->actingAs($this->admin)->put(route('admin.treatment-plans.update', $plan), ['goals' => [['description' => 'New goal']]]);
        // historical session unchanged
        $this->assertEquals('Old ROM', $s->interventions()->first()->name);
        $this->assertEquals('finalized', $s->fresh()->status);
        $this->assertEquals(1, $s->versions()->count());
    }

    /** Scenario C: patient transfer preserves branch attribution. */
    public function test_scenario_c_transfer_preserves_original_branch()
    {
        $enc = app(ClinicalEncounterService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial', 'encounter_at' => now()], $this->physio->id);
        $this->patient->branches()->sync([$this->hq->id => ['event' => 'transferred'], $this->b2->id => ['event' => 'transferred']]);
        $this->patient->update(['primary_branch_id' => $this->b2->id]);
        // B2 session on new plan
        $plan = ClinicalTreatmentPlan::create(['plan_number' => 'TPL-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->b2->id, 'status' => 'active', 'clinical_indication' => 'X', 'created_by' => $this->physio->id]);
        $s = app(TreatmentSessionService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->b2->id, 'clinical_treatment_plan_id' => $plan->id, 'type' => 'routine', 'session_at' => now()], $this->physio->id);
        // original encounter keeps HQ branch; new session keeps B2
        $this->assertEquals($this->hq->id, $enc->fresh()->branch_id);
        $this->assertEquals($this->b2->id, $s->branch_id);
        // B3 staff denied
        $b3staff = $this->staff('Physiotherapist', [$this->b3->id]);
        $this->actingAs($b3staff)->get(route('admin.patients.show', $this->patient))->assertForbidden();
    }

    /** Scenario D: safety context surfaces + adverse events preserved. */
    public function test_scenario_d_safety_and_adverse_events()
    {
        $enc = app(ClinicalEncounterService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial', 'encounter_at' => now()], $this->physio->id);
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), ['red_flags' => [['category' => 'severe_unexplained', 'description' => 'Night pain']]]);
        $plan = app(TreatmentPlanService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_encounter_id' => $enc->id, 'clinical_indication' => 'X'], $this->physio->id);
        // safety context visible on plan show
        $this->actingAs($this->physio)->get(route('admin.treatment-plans.show', $plan))->assertOk();
        // session adverse event preserved
        $plan->update(['status' => 'active']);
        $s = app(TreatmentSessionService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_treatment_plan_id' => $plan->id, 'type' => 'routine', 'session_at' => now()], $this->physio->id);
        $this->actingAs($this->physio)->put(route('admin.sessions.update', $s), ['adverse_events' => [['description' => 'Dizziness', 'action_taken' => 'Stopped', 'escalation' => 'Refer']]]);
        $this->assertEquals(1, $s->adverseEvents()->count());
        $this->assertEquals('Refer', $s->adverseEvents()->first()->escalation);
    }

    /** Scenario E: finalized records immutable; amendments preserve original. */
    public function test_scenario_e_finalized_amendment()
    {
        $enc = app(ClinicalEncounterService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial', 'encounter_at' => now()], $this->physio->id);
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), ['pain' => [['location' => 'Knee', 'nprs_current' => 5]]]);
        $enc->update(['status' => 'in_progress']); $this->actingAs($this->admin)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized']);
        // unauthorized edit rejected
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), ['pain' => [['location' => 'Knee', 'nprs_current' => 9]]]);
        $this->assertEquals(5, $enc->painAssessments()->first()->nprs_current);
        // authorized amend preserves snapshot
        $this->actingAs($this->admin)->post(route('admin.clinical.amend', $enc), ['reason' => 'Score error']);
        $this->assertEquals('amended', $enc->fresh()->status);
        $this->assertEquals('Score error', $enc->versions()->latest('version')->first()->reason);
        $this->assertGreaterThanOrEqual(2, $enc->versions()->count());
        // amended state is editable: correction applied, then re-finalized
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), ['pain' => [['location' => 'Knee', 'nprs_current' => 6]]]);
        $this->assertEquals(6, $enc->painAssessments()->first()->nprs_current);
        $this->actingAs($this->admin)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized']);
        $this->assertEquals('finalized', $enc->fresh()->status);
        $this->assertGreaterThanOrEqual(3, $enc->versions()->count());
    }

    /** Scenario F: role-based authorization matrix. */
    public function test_scenario_f_authorization_matrix()
    {
        $enc = app(ClinicalEncounterService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial', 'encounter_at' => now()], $this->physio->id);
        $plan = app(TreatmentPlanService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_indication' => 'X'], $this->physio->id);
        // receptionist: no clinical create/finalize
        $this->actingAs($this->rec)->post(route('admin.patients.sessions.store', $this->patient), ['clinical_treatment_plan_id' => $plan->id, 'branch_id' => $this->hq->id, 'type' => 'routine', 'session_at' => now()->toDateTimeString()])->assertForbidden();
        $this->actingAs($this->rec)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized'])->assertForbidden();
        // physio: can't approve own plan (already covered M2); doctor approves
        $plan->update(['status' => 'pending_review']);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $plan), ['status' => 'approved']); // physio created it → self-approval guard
        $this->assertEquals('pending_review', $plan->fresh()->status);
        $this->actingAs($this->doctor)->post(route('admin.treatment-plans.transition', $plan), ['status' => 'approved']);
        $this->assertEquals('approved', $plan->fresh()->status);
    }

    /** Concurrent number generation across entities. */
    public function test_number_generation_uniqueness()
    {
        $nums = collect(range(1, 5))->map(fn () => app(TreatmentSessionService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'routine', 'session_at' => now()], $this->physio->id)->session_number);
        $this->assertEquals(5, $nums->unique()->count());
    }

    /** Unauthenticated + unenabled access. */
    public function test_unauthenticated_and_module_gating()
    {
        $this->get(route('admin.clinical.index'))->assertRedirect(route('login'));
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'disabled']);
        $this->actingAs($this->physio)->get(route('admin.sessions.index'))->assertForbidden();
    }
}
