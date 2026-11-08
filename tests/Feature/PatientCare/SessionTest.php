<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentSession;
use Modules\PatientCare\Clinical\Services\TreatmentSessionService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $physio;
    private Branch $hq;
    private Patient $patient;
    private ClinicalTreatmentPlan $plan;

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
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'S', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
        $this->plan = ClinicalTreatmentPlan::create(['plan_number' => 'TPL-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'status' => 'active', 'clinical_indication' => 'Rehab', 'created_by' => $this->physio->id]);
    }

    private function makeSession(string $status = 'draft'): ClinicalTreatmentSession
    {
        $s = app(TreatmentSessionService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'clinical_treatment_plan_id' => $this->plan->id, 'type' => 'routine', 'session_at' => now()], $this->physio->id);
        if ($status !== 'draft') {
            $s->update(['status' => $status]);
        }
        return $s;
    }

    public function test_session_created_with_unique_number()
    {
        $s = $this->makeSession();
        $this->assertMatchesRegularExpression('/^SES-\d{4}-\d{6}$/', $s->session_number);
        $this->assertNotNull($s->notes);
    }

    public function test_store_rejects_inactive_plan()
    {
        $this->plan->update(['status' => 'draft']);
        $this->actingAs($this->physio)->post(route('admin.patients.sessions.store', $this->patient), [
            'clinical_treatment_plan_id' => $this->plan->id, 'branch_id' => $this->hq->id, 'type' => 'routine', 'session_at' => now()->toDateTimeString(),
        ]);
        $this->assertEquals(0, ClinicalTreatmentSession::count());
    }

    public function test_draft_save_all_sections()
    {
        $s = $this->makeSession('in_progress');
        $this->actingAs($this->physio)->put(route('admin.sessions.update', $s), [
            'session' => ['pre_pain' => 7, 'pre_condition' => 'Stiff', 'overall_tolerance' => 'well_tolerated', 'post_pain' => 4],
            'interventions' => [['name' => 'Knee ROM', 'status' => 'performed', 'sets' => 3, 'reps' => 10], ['name' => 'Ultrasound', 'status' => 'not_performed', 'not_performed_reason' => 'equipment']],
            'notes' => ['subjective' => 'Reports pain', 'objective' => 'ROM 90', 'assessment' => 'Improving', 'plan' => 'Continue'],
            'adverse_events' => [['description' => 'Dizziness', 'action_taken' => 'Rest']],
        ]);
        $s = $s->fresh();
        $this->assertEquals(7, $s->pre_pain);
        $this->assertEquals(2, $s->interventions()->count());
        $this->assertEquals('not_performed', $s->interventions()->where('name', 'Ultrasound')->first()->status);
        $this->assertEquals('Improving', $s->notes->assessment);
        $this->assertEquals(1, $s->adverseEvents()->count());
    }

    public function test_nprs_validated()
    {
        $s = $this->makeSession('in_progress');
        $this->actingAs($this->physio)->put(route('admin.sessions.update', $s), ['session' => ['pre_pain' => 15]])
            ->assertSessionHasErrors('session.pre_pain');
    }

    public function test_finalize_creates_snapshot_and_review()
    {
        $s = $this->makeSession('in_progress');
        $this->actingAs($this->admin)->post(route('admin.sessions.transition', $s), ['status' => 'finalized']);
        $s = $s->fresh();
        $this->assertEquals('finalized', $s->status);
        $this->assertEquals(1, $s->versions()->count());
        $this->assertEquals('approved', $s->reviews()->first()->decision);
    }

    public function test_finalized_session_cannot_be_edited()
    {
        $s = $this->makeSession('finalized');
        $this->actingAs($this->admin)->put(route('admin.sessions.update', $s), ['session' => ['pre_pain' => 9]]);
        $this->assertNull($s->fresh()->pre_pain);
    }

    public function test_amend_preserves_original()
    {
        $s = $this->makeSession('in_progress');
        $this->actingAs($this->physio)->put(route('admin.sessions.update', $s), ['session' => ['pre_pain' => 7]]);
        $this->actingAs($this->admin)->post(route('admin.sessions.transition', $s), ['status' => 'finalized']);
        $this->actingAs($this->admin)->post(route('admin.sessions.amend', $s), ['reason' => 'Pain score error']);
        $this->assertEquals('amended', $s->fresh()->status);
        $this->assertEquals('Pain score error', $s->versions()->latest('version')->first()->reason);
    }

    public function test_cancel_requires_reason()
    {
        $s = $this->makeSession('in_progress');
        $this->actingAs($this->admin)->post(route('admin.sessions.transition', $s), ['status' => 'cancelled']);
        $this->assertEquals('in_progress', $s->fresh()->status);
    }

    public function test_goal_progress_linked_to_session()
    {
        $goal = $this->plan->goals()->create(['description' => 'Flex 110', 'baseline' => '70', 'target' => '110', 'status' => 'not_started']);
        $s = $this->makeSession('in_progress');
        $this->actingAs($this->physio)->post(route('admin.sessions.goals.progress', [$s, $goal]), ['value' => '85', 'status' => 'in_progress']);
        $p = $goal->progressEntries()->first();
        $this->assertEquals($s->id, $p->clinical_treatment_session_id);
        $this->assertEquals('in_progress', $goal->fresh()->status);
    }

    public function test_cross_branch_session_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $foreign = Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $other->id]);
        $s = ClinicalTreatmentSession::create(['session_number' => 'SES-2026-000090', 'patient_id' => $foreign->id, 'branch_id' => $other->id, 'type' => 'routine', 'status' => 'draft', 'session_at' => now(), 'created_by' => $this->admin->id]);
        $this->actingAs($this->physio)->get(route('admin.sessions.show', $s))->assertForbidden();
    }

    public function test_unauthorized_cannot_finalize()
    {
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $s = $this->makeSession('in_progress');
        $this->actingAs($staff)->post(route('admin.sessions.transition', $s), ['status' => 'finalized'])->assertForbidden();
    }

    public function test_print_report()
    {
        $s = $this->makeSession('finalized');
        $this->actingAs($this->admin)->get(route('admin.sessions.print', $s))->assertOk();
    }

    public function test_session_audit_logged()
    {
        $this->makeSession();
        $this->assertEquals('clinical.session_created', AuditLog::latest('id')->first()->action);
    }
}
