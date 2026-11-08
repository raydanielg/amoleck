<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Clinical\Models\ClinicalEncounter;
use Modules\PatientCare\Clinical\Services\ClinicalEncounterService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class ClinicalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $physio;
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
        $this->physio = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->physio->assignRole('Physiotherapist');
        $this->physio->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'C', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
    }

    private function encounter(string $status = 'draft'): ClinicalEncounter
    {
        return ClinicalEncounter::create(['encounter_number' => 'ENC-2026-'.str_pad((string) (ClinicalEncounter::count() + 1), 6, '0', STR_PAD_LEFT), 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial_assessment', 'status' => $status, 'physiotherapist_id' => $this->physio->id, 'encounter_at' => now(), 'created_by' => $this->physio->id]);
    }

    public function test_encounter_created_with_unique_number_and_assessment()
    {
        $enc = app(ClinicalEncounterService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial_assessment', 'encounter_at' => now()], $this->physio->id);
        $this->assertMatchesRegularExpression('/^ENC-\d{4}-\d{6}$/', $enc->encounter_number);
        $this->assertNotNull($enc->assessment);
    }

    public function test_draft_saved_and_finalized_with_version()
    {
        $enc = $this->encounter('in_progress');
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), [
            'assessment' => ['mechanism' => 'Fall', 'subjective_summary' => 'Pain'],
            'complaints' => [['complaint' => 'Back pain', 'onset_date' => now()->toDateString()]],
            'pain' => [['location' => 'LBP', 'nprs_current' => 7, 'nprs_best' => 3, 'nprs_worst' => 9]],
        ]);
        $this->assertEquals('Fall', $enc->assessment->fresh()->mechanism);
        $this->assertEquals(1, $enc->complaints()->count());
        $this->assertEquals(7, $enc->painAssessments()->first()->nprs_current);
        $this->actingAs($this->admin)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized']);
        $this->assertEquals('finalized', $enc->fresh()->status);
        $this->assertEquals(1, $enc->versions()->count());
        $this->assertEquals('finalized', $enc->versions()->first()->action);
    }

    public function test_nprs_validated()
    {
        $enc = $this->encounter('in_progress');
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), ['pain' => [['location' => 'x', 'nprs_current' => 15]]])
            ->assertSessionHasErrors('pain.0.nprs_current');
    }

    public function test_finalized_encounter_cannot_be_edited()
    {
        $enc = $this->encounter('finalized');
        $this->actingAs($this->admin)->put(route('admin.patients.clinical.update', $enc), ['assessment' => ['mechanism' => 'Changed']]);
        $this->assertNull($enc->assessment);
    }

    public function test_amendment_preserves_original_version()
    {
        $enc = $this->encounter('in_progress');
        $this->actingAs($this->physio)->put(route('admin.patients.clinical.update', $enc), ['assessment' => ['mechanism' => 'Original']]);
        $this->actingAs($this->admin)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized']);
        $this->actingAs($this->admin)->post(route('admin.clinical.amend', $enc), ['reason' => 'Typo fix', 'notes' => 'Amended note']);
        $this->assertEquals('amended', $enc->fresh()->status);
        $v2 = $enc->versions()->latest('version')->first();
        $this->assertEquals('Typo fix', $v2->reason);
        $this->assertEquals('Original', $v2->snapshot['assessment']['mechanism']);
    }

    public function test_invalid_transition_rejected()
    {
        $enc = $this->encounter('draft');
        $this->actingAs($this->admin)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized']);
        $this->assertEquals('draft', $enc->fresh()->status);
    }

    public function test_cancel_preserves_record()
    {
        $enc = $this->encounter('in_progress');
        $this->actingAs($this->admin)->post(route('admin.clinical.transition', $enc), ['status' => 'cancelled', 'reason' => 'No-show']);
        $this->assertEquals('cancelled', $enc->fresh()->status);
        $this->assertNotNull(ClinicalEncounter::find($enc->id));
    }

    public function test_cross_branch_encounter_access_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $foreign = Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $other->id]);
        $enc = ClinicalEncounter::create(['encounter_number' => 'ENC-2026-000090', 'patient_id' => $foreign->id, 'branch_id' => $other->id, 'type' => 'initial_assessment', 'status' => 'draft', 'encounter_at' => now(), 'created_by' => $this->admin->id]);
        $this->actingAs($this->physio)->get(route('admin.patients.clinical.show', $enc))->assertForbidden();
    }

    public function test_unauthorized_cannot_finalize()
    {
        $enc = $this->encounter('in_progress');
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->actingAs($staff)->post(route('admin.clinical.transition', $enc), ['status' => 'finalized'])->assertForbidden();
    }

    public function test_clinical_dashboard_scope()
    {
        $this->encounter();
        $this->actingAs($this->physio)->get(route('admin.clinical.dashboard'))->assertOk();
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->actingAs($staff)->get(route('admin.clinical.dashboard'))->assertForbidden();
    }

    public function test_encounter_audit_trail()
    {
        app(ClinicalEncounterService::class)->create(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'initial_assessment', 'encounter_at' => now()], $this->admin->id);
        $this->assertEquals('clinical.encounter_created', AuditLog::latest('id')->first()->action);
    }

    public function test_print_report_accessible()
    {
        $enc = $this->encounter('finalized');
        $this->actingAs($this->admin)->get(route('admin.clinical.print', $enc))->assertOk();
    }
}
