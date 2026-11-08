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
use Modules\PatientCare\Models\PatientDuplicateReview;
use Modules\PatientCare\Services\PatientAccessService;
use Tests\TestCase;

class PatientCareTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $hq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
    }

    private function receptionist(): User
    {
        $u = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $u->assignRole('Receptionist');
        $u->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        return $u;
    }

    public function test_patient_registration_generates_unique_mrn()
    {
        $this->actingAs($this->admin)->post(route('admin.patients.store'), ['first_name' => 'Amina', 'last_name' => 'Juma', 'gender' => 'female', 'primary_branch_id' => $this->hq->id])->assertRedirect();
        $p = Patient::first();
        $this->assertMatchesRegularExpression('/^PAT-\d{4}-\d{6}$/', $p->mrn);
        $this->assertEquals($this->admin->id, $p->registered_by);
    }

    public function test_registration_requires_names()
    {
        $this->actingAs($this->admin)->post(route('admin.patients.store'), [])->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_duplicate_warning_then_confirm_creates_review()
    {
        Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '0712', 'primary_branch_id' => $this->hq->id]);
        $this->actingAs($this->admin)->post(route('admin.patients.store'), ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '0712']);
        $this->assertEquals(1, Patient::count());
        $this->actingAs($this->admin)->post(route('admin.patients.store'), ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '0712', 'confirm_duplicates' => 1]);
        $this->assertEquals(2, Patient::count());
        $this->assertEquals(1, PatientDuplicateReview::where('status', 'pending')->count());
    }

    public function test_patient_directory_scoped_to_branch()
    {
        $other = Branch::create(['name' => 'Other', 'code' => 'OB']);
        Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'In', 'last_name' => 'Scope', 'primary_branch_id' => $this->hq->id]);
        Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'Out', 'last_name' => 'Scope', 'primary_branch_id' => $other->id]);
        $rec = $this->receptionist();
        $this->assertTrue(app(PatientAccessService::class)->canAccess($rec, Patient::first()));
        $this->assertFalse(app(PatientAccessService::class)->canAccess($rec, Patient::find(2)));
        $this->actingAs($rec)->get(route('admin.patients.show', 2))->assertForbidden();
    }

    public function test_cross_branch_lookup_via_association()
    {
        $other = Branch::create(['name' => 'Other', 'code' => 'OB2']);
        $patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'Cross', 'last_name' => 'P', 'primary_branch_id' => $other->id]);
        $patient->branches()->sync([$this->hq->id => ['event' => 'associated']]);
        $this->assertTrue(app(PatientAccessService::class)->canAccess($this->receptionist(), $patient));
    }

    public function test_view_all_branches_permission_grants_access()
    {
        $other = Branch::create(['name' => 'Other', 'code' => 'OB3']);
        $p = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'Far', 'last_name' => 'Away', 'primary_branch_id' => $other->id]);
        $this->assertTrue(app(PatientAccessService::class)->canAccess($this->admin, $p));
    }

    public function test_status_change_records_history_and_audit()
    {
        $p = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'S', 'last_name' => 'T', 'primary_branch_id' => $this->hq->id]);
        $this->actingAs($this->admin)->post(route('admin.patients.status', $p), ['status' => 'inactive', 'reason' => 'test']);
        $this->assertEquals('inactive', $p->fresh()->status);
        $this->assertEquals('inactive', $p->statusHistory()->latest('id')->first()->to_status);
        $this->assertEquals('patient.inactive', AuditLog::where('entity_id', $p->id)->latest('id')->first()->action);
    }

    public function test_medical_entry_and_consent_recorded()
    {
        $p = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'M', 'last_name' => 'E', 'primary_branch_id' => $this->hq->id]);
        $this->actingAs($this->admin)->post(route('admin.patients.medical.store', $p), ['type' => 'allergy', 'description' => 'Penicillin', 'source' => 'clinician', 'verified' => 1]);
        $this->assertEquals(1, $p->medicalEntries()->count());
        $this->actingAs($this->admin)->post(route('admin.patients.consents.store', $p), ['type' => 'data_processing', 'status' => 'granted']);
        $this->assertEquals(1, $p->consents()->count());
    }

    public function test_document_upload_and_private_download()
    {
        Storage::fake();
        $p = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'D', 'last_name' => 'O', 'primary_branch_id' => $this->hq->id]);
        $file = UploadedFile::fake()->image('referral.png');
        $this->actingAs($this->admin)->post(route('admin.patients.documents.store', $p), ['category' => 'referral', 'file' => $file]);
        $doc = $p->documents()->first();
        $this->assertNotNull($doc);
        $this->actingAs($this->admin)->get(route('admin.patients.documents.download', [$p, $doc]))->assertOk();
    }

    public function test_document_upload_rejects_executables()
    {
        Storage::fake();
        $p = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'E', 'last_name' => 'X', 'primary_branch_id' => $this->hq->id]);
        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');
        $this->actingAs($this->admin)->post(route('admin.patients.documents.store', $p), ['category' => 'other', 'file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_unauthorized_staff_cannot_view_patients()
    {
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Staff');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->actingAs($staff)->get(route('admin.patients.index'))->assertForbidden();
    }

    public function test_disabled_patientcare_module_rejects_access()
    {
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'disabled']);
        $this->actingAs($this->admin)->get(route('admin.patients.index'))->assertForbidden();
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
    }
}
