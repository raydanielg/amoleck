<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\HospitalProfile;
use Modules\Foundation\Support\BranchContext;
use Tests\TestCase;

class HospitalBranchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
    }

    public function test_hospital_profile_editable()
    {
        $this->actingAs($this->admin)->put(route('admin.hospital.update'), ['name' => 'Amoleck Hospital', 'code' => 'AMH', 'country' => 'Tanzania'])
            ->assertRedirect();
        $this->assertEquals('Amoleck Hospital', HospitalProfile::first()->name);
        $this->assertEquals('AMH', HospitalProfile::first()->code);
    }

    public function test_logo_upload_validation_rejects_non_image()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');
        $this->actingAs($this->admin)->put(route('admin.hospital.update'), ['name' => 'X', 'logo' => $file])
            ->assertSessionHasErrors('logo');
    }

    public function test_logo_upload_accepts_image()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('logo.png', 100, 100);
        $this->actingAs($this->admin)->put(route('admin.hospital.update'), ['name' => 'X', 'logo' => $file])
            ->assertRedirect();
        $this->assertNotNull(HospitalProfile::first()->logo);
    }

    public function test_branch_creation_with_new_fields()
    {
        $this->actingAs($this->admin)->post(route('admin.branches.store'), ['name' => 'Dar Branch', 'code' => 'DAR', 'region' => 'Dar es Salaam', 'ward' => 'Kinondoni', 'description' => 'Coastal branch'])
            ->assertRedirect(route('admin.branches.index'));
        $b = Branch::where('code', 'DAR')->first();
        $this->assertEquals('Kinondoni', $b->ward);
        $this->assertEquals($this->admin->id, $b->created_by);
    }

    public function test_branch_status_transitions()
    {
        $branch = Branch::create(['name' => 'T', 'code' => 'ST', 'status' => 'active']);
        $this->actingAs($this->admin)->post(route('admin.branches.status', $branch), ['status' => 'temporarily_closed']);
        $this->assertEquals('temporarily_closed', $branch->fresh()->status);
        $this->actingAs($this->admin)->post(route('admin.branches.status', $branch), ['status' => 'active']);
        $this->assertEquals('active', $branch->fresh()->status);
        $this->assertEquals('branch.temporarily_closed', AuditLog::where('entity_id', $branch->id)->first()->action);
    }

    public function test_primary_branch_must_be_among_assigned()
    {
        $a = Branch::create(['name' => 'A', 'code' => 'PA']);
        $b = Branch::create(['name' => 'B', 'code' => 'PB']);
        $user = User::factory()->create();
        $user->branches()->sync([$a->id => ['is_primary' => true], $b->id => ['is_primary' => false]]);
        $this->assertEquals($a->id, $user->branches->firstWhere('pivot.is_primary', true)->id);
    }

    public function test_super_admin_can_access_all_branches()
    {
        $branch = Branch::create(['name' => 'Far', 'code' => 'FA']);
        $this->assertTrue(BranchContext::canAccess($this->admin, $branch->id));
        $this->assertEquals($branch->id, BranchContext::allowed($this->admin)->firstWhere('id', $branch->id)->id);
    }

    public function test_branch_restricted_user_cannot_access_unassigned()
    {
        $assigned = Branch::where('code', 'HQ')->first();
        $other = Branch::create(['name' => 'Other', 'code' => 'OT']);
        $user = User::factory()->create(['status' => 'active']);
        $user->branches()->sync([$assigned->id => ['is_primary' => true]]);
        $this->assertFalse(BranchContext::canAccess($user, $other->id));
        $this->assertTrue(BranchContext::canAccess($user, $assigned->id));
    }

    public function test_branch_context_switch_validation()
    {
        $assigned = Branch::where('code', 'HQ')->first();
        $other = Branch::create(['name' => 'Other', 'code' => 'OT2']);
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole('Branch Administrator');
        $user->branches()->sync([$assigned->id => ['is_primary' => true]]);
        $this->actingAs($user)->post(route('admin.branch-context.update'), ['branch_id' => $other->id])
            ->assertForbidden();
        $this->actingAs($user)->post(route('admin.branch-context.update'), ['branch_id' => $assigned->id])
            ->assertRedirect();
        $this->assertEquals($assigned->id, session('current_branch_id'));
    }

    public function test_hospital_update_creates_audit()
    {
        $before = AuditLog::where('action', 'hospital.updated')->count();
        $this->actingAs($this->admin)->put(route('admin.hospital.update'), ['name' => 'New Name']);
        $this->assertEquals($before + 1, AuditLog::where('action', 'hospital.updated')->count());
    }
}
