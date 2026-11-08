<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Support\BranchContext;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
    }

    public function test_username_login_works()
    {
        $user = User::factory()->create(['username' => 'jdoe', 'status' => 'active', 'email_verified_at' => now()]);
        $this->post(route('login.store'), ['email' => 'jdoe', 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_locked_status_user_cannot_login()
    {
        $user = User::factory()->create(['status' => 'locked']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors();
    }

    public function test_locked_until_account_cannot_login()
    {
        $user = User::factory()->create(['status' => 'active', 'locked_until' => now()->addMinutes(10)]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors();
    }

    public function test_staff_creation_auto_generates_employee_number()
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Jane Doe', 'email' => 'jane@test.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect();
        $user = User::where('email', 'jane@test.com')->first();
        $this->assertStringStartsWith('EMP-', $user->employee_number);
    }

    public function test_staff_creation_without_password_sends_reset_link()
    {
        Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'No Pass', 'email' => 'nopass@test.com',
        ])->assertRedirect();
    }

    public function test_duplicate_employee_number_rejected()
    {
        User::factory()->create(['employee_number' => 'EMP-0001']);
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Dup', 'email' => 'dup@test.com', 'employee_number' => 'EMP-0001',
        ])->assertSessionHasErrors('employee_number');
    }

    public function test_bulk_status_updates_accounts()
    {
        $a = User::factory()->create(['status' => 'active']);
        $b = User::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin)->post(route('admin.users.bulk-status'), [
            'user_ids' => [$a->id, $b->id], 'status' => 'suspended',
        ])->assertRedirect();
        $this->assertEquals('suspended', $a->fresh()->status);
        $this->assertEquals('suspended', $b->fresh()->status);
        $this->assertEquals(2, AuditLog::where('action', 'user.suspended')->count());
    }

    public function test_branch_admin_cannot_manage_other_branch_staff()
    {
        $hq = Branch::where('code', 'HQ')->first();
        $other = Branch::create(['name' => 'Other', 'code' => 'OB']);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $admin->assignRole('Branch Administrator');
        $admin->branches()->sync([$hq->id => ['is_primary' => true]]);
        $target = User::factory()->create();
        $target->branches()->sync([$other->id => ['is_primary' => true]]);
        $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.status', $target), ['status' => 'suspended'])->assertForbidden();
    }

    public function test_branch_admin_can_manage_own_branch_staff()
    {
        $hq = Branch::where('code', 'HQ')->first();
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $admin->assignRole('Branch Administrator');
        $admin->branches()->sync([$hq->id => ['is_primary' => true]]);
        $target = User::factory()->create();
        $target->branches()->sync([$hq->id => ['is_primary' => true]]);
        $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertOk();
    }

    public function test_status_change_is_audited()
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin)->post(route('admin.users.status', $user), ['status' => 'suspended']);
        $this->assertEquals('user.suspended', AuditLog::where('entity_id', $user->id)->where('action', 'like', 'user.%')->first()->action);
    }

    public function test_profile_photo_rejects_non_image()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Photo', 'email' => 'photo@test.com', 'profile_photo' => $file,
        ])->assertSessionHasErrors('profile_photo');
    }

    public function test_password_not_stored_in_audit()
    {
        $user = User::factory()->create();
        $this->actingAs($this->admin)->post(route('admin.users.reset-password', $user), ['password' => 'newsecret123', 'password_confirmation' => 'newsecret123']);
        $this->assertStringNotContainsString('newsecret123', json_encode(AuditLog::latest('id')->first()->toArray()));
    }
}
