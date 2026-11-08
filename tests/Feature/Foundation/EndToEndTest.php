<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\LoginHistory;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\SystemSettingsService;
use Tests\TestCase;

class EndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        app(\Modules\Foundation\Services\ModuleRegistryService::class)->sync();
    }

    private function userWith(string $role, array $attrs = []): User
    {
        $u = User::factory()->create(['status' => 'active', 'email_verified_at' => now()] + $attrs);
        $u->assignRole($role);
        $u->branches()->sync([Branch::where('code', 'HQ')->first()->id => ['is_primary' => true]]);
        return $u;
    }

    public function test_scenario_a_super_admin_full_access()
    {
        foreach (['admin.dashboard', 'admin.hospital.edit', 'admin.branches.index', 'admin.users.index', 'admin.roles.index', 'admin.modules.index', 'admin.settings.edit', 'admin.audit-logs.index', 'admin.login-history.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }

    public function test_scenario_b_hospital_admin_cannot_manage_modules_or_secrets()
    {
        $ha = $this->userWith('Hospital Administrator');
        $this->actingAs($ha)->get(route('admin.branches.index'))->assertOk();
        $this->actingAs($ha)->get(route('admin.modules.index'))->assertOk();
        $mod = SystemModule::where('identifier', 'patientcare')->firstOrFail();
        $this->actingAs($ha)->post(route('admin.modules.toggle', 'PatientCare'))->assertForbidden();
    }

    public function test_scenario_c_branch_admin_scope()
    {
        $ba = $this->userWith('Branch Administrator');
        $this->actingAs($ba)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($ba)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($ba)->get(route('admin.settings.edit'))->assertForbidden();
        $other = Branch::create(['name' => 'Other', 'code' => 'OX']);
        $target = User::factory()->create();
        $target->branches()->sync([$other->id => ['is_primary' => true]]);
        $this->actingAs($ba)->get(route('admin.users.edit', $target))->assertForbidden();
    }

    public function test_scenario_d_general_staff_denied_admin()
    {
        $staff = $this->userWith('Staff');
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.branches.index'))->assertForbidden();
    }

    public function test_scenario_e_suspended_loses_session_access()
    {
        $user = $this->userWith('Staff');
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $user->update(['status' => 'suspended']);
        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_redirected_to_login()
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_idor_branch_context_rejected()
    {
        $user = $this->userWith('Staff');
        $other = Branch::create(['name' => 'Sneaky', 'code' => 'SN']);
        $this->actingAs($user)->post(route('admin.branch-context.update'), ['branch_id' => $other->id])->assertForbidden();
    }

    public function test_privilege_escalation_via_crafted_role_store()
    {
        $staff = $this->userWith('Staff');
        $this->actingAs($staff)->post(route('admin.roles.store'), ['name' => 'Root2', 'permissions' => ['modules.manage']])->assertForbidden();
    }

    public function test_full_hospital_setup_flow()
    {
        $this->actingAs($this->admin)->put(route('admin.hospital.update'), ['name' => 'Amoleck Physio', 'code' => 'APH']);
        $this->actingAs($this->admin)->post(route('admin.branches.store'), ['name' => 'Arusha', 'code' => 'ARU']);
        $branch = Branch::where('code', 'ARU')->firstOrFail();
        $staff = User::factory()->create();
        $staff->branches()->sync([$branch->id => ['is_primary' => true]]);
        $this->assertEquals($branch->id, $staff->fresh()->branches->firstWhere('pivot.is_primary', true)->id);
        $this->assertEquals('APH', \Modules\Foundation\Models\HospitalProfile::first()->code);
    }

    public function test_disabled_module_route_is_rejected()
    {
        SystemModule::where('identifier', 'foundation')->update(['status' => 'maintenance']);
        $staff = $this->userWith('Staff');
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        SystemModule::where('identifier', 'foundation')->update(['status' => 'active']);
    }

    public function test_login_records_history()
    {
        $user = $this->userWith('Staff');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->assertEquals('login', LoginHistory::where('user_id', $user->id)->latest('id')->first()->event);
    }

    public function test_settings_precedence_and_audit_integration()
    {
        $svc = app(SystemSettingsService::class);
        $hq = Branch::where('code', 'HQ')->first();
        $svc->set('app.currency', 'USD', null, $this->admin);
        $svc->set('app.currency', 'KES', $hq->id, $this->admin);
        $this->assertEquals('KES', $svc->get('app.currency', $hq->id));
        $this->assertEquals(2, AuditLog::where('action', 'setting.updated')->count());
    }
}
