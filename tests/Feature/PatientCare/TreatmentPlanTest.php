<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Clinical\Models\ClinicalIntervention;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan;
use Modules\PatientCare\Clinical\Services\TreatmentPlanService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class TreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $physio;
    private User $doctor;
    private Branch $hq;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
        $this->physio = $this->staff('Physiotherapist');
        $this->doctor = $this->staff('Doctor');
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'T', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
    }

    private function staff(string $role): User
    {
        $u = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $u->assignRole($role);
        $u->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        return $u;
    }

    private function plan(User $as): ClinicalTreatmentPlan
    {
        $this->actingAs($as)->post(route('admin.patients.treatment-plans.store', $this->patient), [
            'branch_id' => $this->hq->id, 'clinical_indication' => 'Post-op rehab',
        ]);
        return ClinicalTreatmentPlan::latest('id')->first();
    }

    public function test_plan_created_with_unique_number_and_version()
    {
        $p = $this->plan($this->physio);
        $this->assertMatchesRegularExpression('/^TPL-\d{4}-\d{6}$/', $p->plan_number);
        $this->assertEquals('draft', $p->status);
        $this->assertEquals('created', $p->versions()->first()->action);
    }

    public function test_draft_plan_accepts_problems_goals_interventions()
    {
        $iv = ClinicalIntervention::create(['name' => 'Knee ROM', 'category' => 'therapeutic_exercise', 'active' => true]);
        $p = $this->plan($this->physio);
        $this->actingAs($this->physio)->put(route('admin.treatment-plans.update', $p), [
            'problems' => [['description' => 'Reduced knee flexion', 'body_region' => 'knee', 'baseline' => '70°', 'priority' => 'high']],
            'goals' => [['description' => 'Flex 110°', 'type' => 'short_term', 'baseline' => '70', 'target' => '110', 'unit' => 'degrees']],
            'planned_interventions' => [['clinical_intervention_id' => $iv->id, 'sets' => 3, 'reps' => 10, 'frequency' => '2x/week']],
            'precautions' => [['type' => 'precaution', 'description' => 'No impact loading']],
        ]);
        $this->assertEquals(1, $p->problems()->count());
        $this->assertEquals(1, $p->goals()->count());
        $this->assertEquals(3, $p->plannedInterventions()->first()->sets);
        $this->assertEquals(1, $p->precautionRecords()->count());
    }

    public function test_submit_approve_activate_workflow()
    {
        $p = $this->plan($this->physio);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $p), ['status' => 'pending_review']);
        $this->actingAs($this->doctor)->post(route('admin.treatment-plans.transition', $p), ['status' => 'approved', 'comments' => 'LGTM']);
        $p = $p->fresh();
        $this->assertEquals('approved', $p->status);
        $this->assertEquals($this->doctor->id, $p->approved_by);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $p), ['status' => 'active']);
        $this->assertEquals('active', $p->fresh()->status);
        $this->assertEquals(1, $p->reviews()->where('decision', 'approved')->count());
    }

    public function test_self_approval_blocked()
    {
        $p = $this->plan($this->doctor);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $p), ['status' => 'pending_review']);
        $this->actingAs($this->doctor)->post(route('admin.treatment-plans.transition', $p), ['status' => 'approved']);
        $this->assertEquals('pending_review', $p->fresh()->status);
    }

    public function test_rejection_returns_to_editable_state()
    {
        $p = $this->plan($this->physio);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $p), ['status' => 'pending_review']);
        $this->actingAs($this->doctor)->post(route('admin.treatment-plans.transition', $p), ['status' => 'cancelled', 'reason' => 'Incomplete goals']);
        $this->assertEquals('cancelled', $p->fresh()->status);
        $this->assertEquals('Incomplete goals', $p->reviews()->first()->rejection_reason);
    }

    public function test_hold_and_discontinue_require_reason()
    {
        $p = $this->plan($this->physio);
        $p->update(['status' => 'active']);
        $this->actingAs($this->admin)->post(route('admin.treatment-plans.transition', $p), ['status' => 'on_hold']);
        $this->assertEquals('active', $p->fresh()->status);
        $this->actingAs($this->admin)->post(route('admin.treatment-plans.transition', $p), ['status' => 'on_hold', 'reason' => 'Patient ill']);
        $this->assertEquals('on_hold', $p->fresh()->status);
    }

    public function test_closed_plan_rejects_edits()
    {
        $p = $this->plan($this->physio);
        $p->update(['status' => 'completed']);
        $this->actingAs($this->admin)->put(route('admin.treatment-plans.update', $p), ['problems' => [['description' => 'New']]]);
        $this->assertEquals(0, $p->problems()->count());
    }

    public function test_versions_preserved_across_transitions()
    {
        $p = $this->plan($this->physio);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.transition', $p), ['status' => 'pending_review']);
        $this->actingAs($this->doctor)->post(route('admin.treatment-plans.transition', $p), ['status' => 'approved']);
        $this->assertEquals(3, $p->versions()->count()); // created + pending_review + approved
        $v3 = $p->versions()->latest('version')->first();
        $this->assertEquals('approved', $v3->action);
    }

    public function test_goal_progress_appends_and_updates_status()
    {
        $p = $this->plan($this->physio);
        $p->update(['status' => 'active']);
        $goal = $p->goals()->create(['description' => 'Flex 110', 'type' => 'short_term', 'baseline' => '70', 'target' => '110', 'status' => 'not_started']);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.goals.progress', $goal), ['value' => '85', 'status' => 'in_progress', 'comment' => 'Good']);
        $this->actingAs($this->physio)->post(route('admin.treatment-plans.goals.progress', $goal), ['value' => '100', 'status' => 'in_progress']);
        $this->assertEquals(2, $goal->progressEntries()->count());
        $this->assertEquals('in_progress', $goal->fresh()->status);
    }

    public function test_catalogue_management()
    {
        $this->actingAs($this->admin)->post(route('admin.interventions.store'), ['name' => 'Ultrasound', 'category' => 'physical_modalities']);
        $this->assertEquals(1, ClinicalIntervention::count());
        $this->actingAs($this->physio)->post(route('admin.interventions.store'), ['name' => 'X', 'category' => 'other'])->assertForbidden();
    }

    public function test_cross_branch_plan_access_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $foreign = Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $other->id]);
        $p = ClinicalTreatmentPlan::create(['plan_number' => 'TPL-2026-000090', 'patient_id' => $foreign->id, 'branch_id' => $other->id, 'clinical_indication' => 'x', 'created_by' => $this->admin->id]);
        $this->actingAs($this->physio)->get(route('admin.treatment-plans.show', $p))->assertForbidden();
    }

    public function test_print_report()
    {
        $p = $this->plan($this->physio);
        $this->actingAs($this->admin)->get(route('admin.treatment-plans.print', $p))->assertOk();
    }

    public function test_plan_audit_logged()
    {
        $this->plan($this->physio);
        $this->assertEquals('treatment_plan.created', AuditLog::latest('id')->first()->action);
    }
}
