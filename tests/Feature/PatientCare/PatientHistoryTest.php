<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Models\PatientReferral;
use Modules\PatientCare\Services\ReferralService;
use Tests\TestCase;

class PatientHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
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
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'Hist', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
    }

    public function test_condition_recorded_with_verification_flag()
    {
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/history/condition", [
            'name' => 'Asthma', 'status' => 'active', 'source' => 'clinician', 'verified' => 1,
        ]);
        $c = $this->patient->conditions()->first();
        $this->assertEquals('Asthma', $c->name);
        $this->assertTrue($c->verified);
        $this->assertEquals('clinician', $c->source);
    }

    public function test_medication_current_and_discontinued()
    {
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/history/medication", [
            'medication' => 'Ibuprofen', 'dosage' => '400mg', 'status' => 'current', 'source' => 'clinician',
        ]);
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/history/medication", [
            'medication' => 'Old Med', 'status' => 'discontinued', 'ended_at' => '2025-01-01', 'source' => 'patient_reported',
        ]);
        $this->assertEquals(1, $this->patient->medicationHistories()->where('status', 'current')->count());
        $this->assertEquals(1, $this->patient->medicationHistories()->where('status', 'discontinued')->count());
    }

    public function test_allergy_status_values()
    {
        foreach (['known', 'no_known', 'not_assessed'] as $status) {
            $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/allergy-status", ['allergy_status' => $status]);
            $this->assertEquals($status, $this->patient->fresh()->allergy_status);
        }
    }

    public function test_amendment_preserves_original()
    {
        $c = $this->patient->conditions()->create(['name' => 'Asthma', 'status' => 'active', 'source' => 'clinician', 'recorded_by' => $this->admin->id]);
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/history/condition/{$c->id}/amend", [
            'reason' => 'Correction', 'fields' => ['name' => 'COPD'],
        ]);
        $this->assertEquals('COPD', $c->fresh()->name);
        $amend = $this->patient->recordAmendments()->first();
        $this->assertEquals('Asthma', $amend->changes['name']['from']);
        $this->assertEquals('Correction', $amend->reason);
    }

    public function test_referral_created_with_unique_number()
    {
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/referrals", [
            'type' => 'external', 'referral_date' => now()->toDateString(), 'reason' => 'Physio assessment', 'receiving_branch_id' => $this->hq->id,
        ]);
        $r = PatientReferral::first();
        $this->assertMatchesRegularExpression('/^REF-\d{4}-\d{6}$/', $r->ref_number);
        $this->assertEquals('submitted', $r->status);
    }

    public function test_referral_full_workflow()
    {
        $ref = PatientReferral::create(['ref_number' => 'REF-2026-000001', 'patient_id' => $this->patient->id, 'type' => 'external', 'referral_date' => now(), 'status' => 'submitted', 'receiving_branch_id' => $this->hq->id]);
        foreach (['received' => null, 'under_review' => null, 'accepted' => null, 'completed' => null] as $status => $reason) {
            $this->actingAs($this->admin)->post("/admin/patients/referrals/{$ref->id}/transition", ['status' => $status, 'reason' => $reason]);
        }
        $this->assertEquals('completed', $ref->fresh()->status);
        $this->assertEquals(4, $ref->statusHistories()->count());
    }

    public function test_rejection_requires_reason()
    {
        $ref = PatientReferral::create(['ref_number' => 'REF-2026-000001', 'patient_id' => $this->patient->id, 'type' => 'external', 'referral_date' => now(), 'status' => 'submitted']);
        $this->actingAs($this->admin)->post("/admin/patients/referrals/{$ref->id}/transition", ['status' => 'rejected']);
        $this->assertEquals('submitted', $ref->fresh()->status);
    }

    public function test_invalid_transition_rejected()
    {
        $ref = PatientReferral::create(['ref_number' => 'REF-2026-000001', 'patient_id' => $this->patient->id, 'type' => 'external', 'referral_date' => now(), 'status' => 'completed']);
        $this->actingAs($this->admin)->post("/admin/patients/referrals/{$ref->id}/transition", ['status' => 'accepted']);
        $this->assertEquals('completed', $ref->fresh()->status);
    }

    public function test_referral_transition_permission_denied()
    {
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $ref = PatientReferral::create(['ref_number' => 'REF-2026-000001', 'patient_id' => $this->patient->id, 'type' => 'external', 'referral_date' => now(), 'status' => 'submitted']);
        $this->actingAs($staff)->post("/admin/patients/referrals/{$ref->id}/transition", ['status' => 'accepted'])->assertForbidden();
    }

    public function test_document_verify_and_replace_preserves_version()
    {
        Storage::fake();
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/documents", [
            'category' => 'referral', 'file' => UploadedFile::fake()->image('orig.png'), 'title' => 'Letter', 'document_date' => now()->toDateString(),
        ]);
        $doc = $this->patient->documents()->first();
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/documents/{$doc->id}/verify");
        $this->assertEquals('verified', $doc->fresh()->verification_status);
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/documents/{$doc->id}/replace", [
            'file' => UploadedFile::fake()->image('new.png'), 'reason' => 'Better scan',
        ]);
        $this->assertEquals(1, $doc->versions()->count());
        $this->assertEquals('unverified', $doc->fresh()->verification_status);
    }

    public function test_branch_transfer_preserves_mrn_and_registration()
    {
        $other = Branch::create(['name' => 'Other', 'code' => 'OB']);
        $this->actingAs($this->admin)->post("/admin/patients/{$this->patient->id}/transfer", ['to_branch_id' => $other->id, 'reason' => 'Relocation']);
        $p = $this->patient->fresh();
        $this->assertEquals('PAT-2026-000001', $p->mrn);
        $this->assertEquals($other->id, $p->primary_branch_id);
        $t = $p->branchTransfers()->first();
        $this->assertEquals($this->hq->id, $t->from_branch_id);
        $this->assertEquals($other->id, $t->to_branch_id);
    }

    public function test_unauthorized_transfer_denied()
    {
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $other = Branch::create(['name' => 'Other', 'code' => 'OB']);
        $this->actingAs($staff)->post("/admin/patients/{$this->patient->id}/transfer", ['to_branch_id' => $other->id])->assertForbidden();
    }

    public function test_timeline_shows_events()
    {
        $this->actingAs($this->admin)->get("/admin/patients/{$this->patient->id}/timeline")->assertOk();
    }

    public function test_referral_index_permission()
    {
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Staff');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->actingAs($staff)->get('/admin/referrals')->assertForbidden();
    }
}
