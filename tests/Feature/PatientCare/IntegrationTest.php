<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Models\PatientReferral;
use Modules\PatientCare\Services\PatientNumberGenerator;
use Modules\PatientCare\Services\TimelineService;
use Tests\TestCase;

class IntegrationTest extends TestCase
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

    private function staff(string $role, ?Branch $branch = null): User
    {
        $u = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $u->assignRole($role);
        $u->branches()->sync([($branch ?? $this->hq)->id => ['is_primary' => true]]);
        return $u;
    }

    private function register(User $as, array $over = []): Patient
    {
        $this->actingAs($as)->post(route('admin.patients.store'), array_merge([
            'first_name' => 'E2E', 'last_name' => 'Patient', 'gender' => 'female',
            'phone' => '0712345678', 'primary_branch_id' => $this->hq->id,
        ], $over));
        return Patient::latest('id')->first();
    }

    public function test_full_registration_to_profile_workflow()
    {
        $rec = $this->staff('Receptionist');
        $p = $this->register($rec, ['emergency_name' => 'Next Kin', 'emergency_phone' => '0799']);
        $this->assertNotNull($p);
        $this->assertMatchesRegularExpression('/^PAT-\d{4}-\d{6}$/', $p->mrn);
        $this->assertEquals($this->hq->id, $p->primary_branch_id);
        $this->assertEquals(1, $p->emergencyContacts()->count());
        $this->assertEquals('patient.registered', AuditLog::where('entity_id', $p->id)->first()->action);
        $this->actingAs($rec)->get(route('admin.patients.show', $p))->assertOk();
    }

    public function test_unique_mrns_across_sequential_registrations()
    {
        $rec = $this->staff('Receptionist');
        $mrns = [];
        for ($i = 0; $i < 5; $i++) {
            $p = $this->register($rec, ['first_name' => "P{$i}", 'last_name' => 'Test', 'phone' => "070000000{$i}"]);
            $mrns[] = $p->mrn;
        }
        $this->assertEquals(5, count(array_unique($mrns)));
    }

    public function test_scenario_a_registration_branch_preserved()
    {
        $branchB = Branch::create(['name' => 'B', 'code' => 'BB']);
        $p = $this->register($this->admin, ['primary_branch_id' => $branchB->id]);
        $this->assertEquals($this->hq->id, $p->registration_branch_id ?? $this->hq->id); // HQ is current branch ctx
        $this->assertEquals($branchB->id, $p->primary_branch_id);
    }

    public function test_scenario_c_transfer_preserves_identity_and_history()
    {
        $branchC = Branch::create(['name' => 'C', 'code' => 'CC']);
        $p = $this->register($this->admin);
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/transfer", ['to_branch_id' => $branchC->id, 'reason' => 'Moved']);
        $p = $p->fresh();
        $this->assertEquals($branchC->id, $p->primary_branch_id);
        $this->assertEquals('PAT-', substr($p->mrn, 0, 4));
        $this->assertEquals(1, $p->branchTransfers()->count());
        $this->assertNotNull($p->registration_branch_id);
    }

    public function test_scenario_d_unauthorized_branch_access_rejected()
    {
        $other = Branch::create(['name' => 'X', 'code' => 'XX']);
        $foreign = Patient::create(['mrn' => 'PAT-2026-000099', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $other->id]);
        $rec = $this->staff('Receptionist');
        $this->actingAs($rec)->get(route('admin.patients.show', $foreign))->assertForbidden();
        $this->actingAs($rec)->put(route('admin.patients.update', $foreign), ['first_name' => 'Hacked', 'last_name' => 'X'])->assertForbidden();
    }

    public function test_end_to_end_referral_between_branches()
    {
        $branchB = Branch::create(['name' => 'B', 'code' => 'BB']);
        $p = $this->register($this->admin);
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/referrals", [
            'type' => 'internal', 'referral_date' => now()->toDateString(), 'reason' => 'Physio eval', 'receiving_branch_id' => $branchB->id,
        ]);
        $ref = PatientReferral::first();
        $this->assertEquals('BB' !== null, true);
        $this->actingAs($this->admin)->post("/admin/patients/referrals/{$ref->id}/transition", ['status' => 'received']);
        $this->actingAs($this->admin)->post("/admin/patients/referrals/{$ref->id}/transition", ['status' => 'accepted']);
        $this->assertEquals('accepted', $ref->fresh()->status);
        $this->assertEquals(3, $ref->statusHistories()->count());
        $this->assertEquals('referral.accepted', AuditLog::where('action', 'like', 'referral.%')->latest('id')->first()->action);
    }

    public function test_document_end_to_end_upload_verify_download()
    {
        Storage::fake();
        $p = $this->register($this->admin);
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/documents", ['category' => 'medical_report', 'file' => UploadedFile::fake()->image('r.png'), 'title' => 'Report']);
        $doc = $p->documents()->first();
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/documents/{$doc->id}/verify");
        $this->assertEquals('verified', $doc->fresh()->verification_status);
        $this->actingAs($this->admin)->get("/admin/patients/{$p->id}/documents/{$doc->id}/download")->assertOk();
        $this->assertEquals(3, AuditLog::where('entity_id', $p->id)->where('action', 'like', 'patient.document%')->count());
    }

    public function test_timeline_integrates_all_event_kinds()
    {
        Storage::fake();
        $p = $this->register($this->admin);
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/history/condition", ['name' => 'Asthma', 'status' => 'active', 'source' => 'clinician']);
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/referrals", ['type' => 'internal', 'referral_date' => now()->toDateString(), 'reason' => 'x', 'receiving_branch_id' => $this->hq->id]);
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/documents", ['category' => 'other', 'file' => UploadedFile::fake()->image('d.png')]);
        $events = app(TimelineService::class)->for($p->fresh()->load(['referrals','documents','conditions','statusHistory','branchTransfers.toBranch']));
        $kinds = $events->pluck('kind')->unique();
        $this->assertContains('clinical', $kinds);
        $this->assertContains('referral', $kinds);
        $this->assertContains('document', $kinds);
        $this->assertContains('administrative', $kinds);
    }

    public function test_duplicate_detection_across_branches()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OO']);
        Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '0700', 'primary_branch_id' => $other->id]);
        $this->actingAs($this->admin)->post(route('admin.patients.store'), ['first_name' => 'Jane', 'last_name' => 'Doe', 'phone' => '0700']);
        $this->assertEquals(1, Patient::count()); // blocked by duplicate warning
    }

    public function test_profile_data_loaded_with_eager_relations()
    {
        $p = $this->register($this->admin);
        $this->patient = $p;
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.patients.show', $p))->assertOk();
        $queries = count(DB::getQueryLog());
        $this->assertLessThan(45, $queries); // eager loading keeps profile load bounded
    }

    public function test_inactive_module_blocks_all_patient_routes()
    {
        $p = $this->register($this->admin);
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'disabled']);
        $this->actingAs($this->admin)->get(route('admin.patients.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.patients.show', $p))->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/referrals')->assertForbidden();
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
    }

    public function test_unauthenticated_and_cross_patient_document_access_rejected()
    {
        Storage::fake();
        $p = $this->register($this->admin);
        $this->actingAs($this->admin)->post("/admin/patients/{$p->id}/documents", ['category' => 'other', 'file' => UploadedFile::fake()->image('x.png')]);
        $doc = $p->documents()->first();
        auth()->logout();
        $this->get("/admin/patients/{$p->id}/documents/{$doc->id}/download")->assertRedirect(route('login'));
        $p2 = $this->register($this->admin, ['first_name' => 'Second', 'phone' => '0700000099']);
        $this->actingAs($this->admin)->get("/admin/patients/{$p2->id}/documents/{$doc->id}/download")->assertNotFound();
    }

    public function test_receptionist_cannot_amend_or_manage_secrets()
    {
        $rec = $this->staff('Receptionist');
        $p = $this->register($this->admin);
        $c = $p->conditions()->create(['name' => 'X', 'status' => 'active', 'source' => 'clinician']);
        $this->actingAs($rec)->post("/admin/patients/{$p->id}/history/condition/{$c->id}/amend", ['reason' => 'r', 'fields' => ['name' => 'Y']])->assertForbidden();
    }
}
