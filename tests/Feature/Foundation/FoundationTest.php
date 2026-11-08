<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
    }

    public function test_suspended_user_cannot_login()
    {
        $user = User::factory()->create(['status' => 'suspended']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors();
    }

    public function test_staff_without_permission_cannot_access_admin_routes()
    {
        $staff = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
        $staff->assignRole('Staff');
        $this->actingAs($staff)->get(route('admin.branches.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_super_admin_can_manage_admin_routes()
    {
        $this->actingAs($this->admin)->get(route('admin.branches.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.modules.index'))->assertOk();
    }

    public function test_branch_code_must_be_unique()
    {
        Branch::create(['name' => 'X', 'code' => 'DUP']);
        $this->actingAs($this->admin)
            ->post(route('admin.branches.store'), ['name' => 'Y', 'code' => 'DUP'])
            ->assertSessionHasErrors('code');
    }

    public function test_last_super_admin_cannot_be_suspended()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.status', $this->admin), ['status' => 'suspended'])
            ->assertSessionHasErrors();
        $this->assertEquals('active', $this->admin->fresh()->status);
    }

    public function test_protected_role_cannot_be_deleted()
    {
        $role = Role::where('name', 'Super Admin')->firstOrFail();
        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertForbidden();
    }

    public function test_foundation_module_cannot_be_disabled()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.modules.toggle', 'Foundation'))
            ->assertForbidden();
    }

    public function test_audit_records_created_on_branch_write()
    {
        $before = AuditLog::count();
        $this->actingAs($this->admin)
            ->post(route('admin.branches.store'), ['name' => 'Clinic B', 'code' => 'CLB']);
        $this->assertGreaterThan($before, AuditLog::count());
        $this->assertEquals('branch.created', AuditLog::latest('id')->first()->action);
    }

    public function test_user_can_have_multiple_branches()
    {
        $a = Branch::create(['name' => 'A', 'code' => 'BA']);
        $b = Branch::create(['name' => 'B', 'code' => 'BB']);
        $user = User::factory()->create();
        $user->branches()->sync([$a->id => ['is_primary' => true], $b->id => ['is_primary' => false]]);
        $this->assertCount(2, $user->fresh()->branches);
    }
}
