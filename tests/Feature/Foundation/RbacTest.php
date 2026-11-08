<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
    }

    public function test_custom_role_lifecycle()
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), ['name' => 'Night Nurse', 'permissions' => ['dashboard.view']])
            ->assertRedirect();
        $role = Role::where('name', 'Night Nurse')->firstOrFail();
        $this->assertEquals('night-nurse', $role->slug);
        $this->assertTrue($role->hasPermissionTo('dashboard.view'));
        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertRedirect();
        $this->assertNull(Role::find($role->id));
    }

    public function test_role_in_use_cannot_be_deleted()
    {
        $role = Role::where('name', 'Staff')->firstOrFail();
        User::factory()->create()->assignRole($role);
        $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertForbidden();
    }

    public function test_unknown_permissions_rejected_on_role_save()
    {
        $role = Role::where('name', 'Staff')->firstOrFail();
        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'Staff', 'permissions' => ['fake.permission'],
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_direct_user_permissions_grant_and_revoke()
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Staff');
        $this->assertFalse($user->hasPermissionTo('branches.view'));
        $this->actingAs($this->admin)->put(route('admin.users.permissions.update', $user), ['permissions' => ['branches.view']]);
        $this->assertTrue($user->fresh()->hasPermissionTo('branches.view'));
        $this->actingAs($this->admin)->put(route('admin.users.permissions.update', $user), ['permissions' => []]);
        $this->assertFalse($user->fresh()->hasPermissionTo('branches.view'));
    }

    public function test_effective_permissions_merge_role_and_direct()
    {
        $user = User::factory()->create();
        $user->assignRole('Staff');
        $user->givePermissionTo('branches.view');
        $all = $user->getAllPermissions()->pluck('name');
        $this->assertContains('dashboard.view', $all);
        $this->assertContains('branches.view', $all);
    }

    public function test_direct_permissions_cannot_be_set_on_super_admin()
    {
        $this->actingAs($this->admin)->put(route('admin.users.permissions.update', $this->admin), ['permissions' => ['branches.view']])
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_manage_roles()
    {
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Staff');
        $this->actingAs($staff)->post(route('admin.roles.store'), ['name' => 'Hack'])->assertForbidden();
    }

    public function test_protected_role_cannot_be_deactivated_or_renamed()
    {
        $role = Role::where('name', 'Super Admin')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.roles.status', $role), ['status' => 'inactive'])->assertForbidden();
        $this->actingAs($this->admin)->put(route('admin.roles.update', $role), ['name' => 'Root', 'permissions' => []])->assertForbidden();
    }

    public function test_direct_permission_does_not_bypass_branch_restriction()
    {
        $other = Branch::create(['name' => 'Elsewhere', 'code' => 'EW']);
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole('Branch Administrator');
        $user->givePermissionTo('branches.view');
        $this->assertFalse(\Modules\Foundation\Support\BranchContext::canAccess($user, $other->id));
    }

    public function test_crafted_request_cannot_assign_unregistered_permission()
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin)->put(route('admin.users.permissions.update', $user), ['permissions' => ['god.mode']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_registry_contains_future_module_namespaces()
    {
        $flat = PermissionCatalog::flat();
        $this->assertContains('patientcare.patients.view', $flat);
        $this->assertContains('finance.invoices.view', $flat);
        $this->assertContains('reporting.analytics.view', $flat);
    }
}
