<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\Setting;
use Modules\Foundation\Services\SystemSettingsService;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private SystemSettingsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->svc = app(SystemSettingsService::class);
    }

    public function test_defaults_resolved_when_no_row()
    {
        $this->assertEquals('en', $this->svc->get('app.locale'));
        $this->assertEquals('Africa/Dar_es_Salaam', $this->svc->get('app.timezone'));
        $this->assertEquals('TZS', $this->svc->get('app.currency'));
        $this->assertEquals('TSh', $this->svc->get('app.currency_symbol'));
    }

    public function test_typed_casting()
    {
        $this->svc->set('auth.max_failed_logins', '7', null, $this->admin);
        $this->assertSame(7, $this->svc->get('auth.max_failed_logins'));
        $this->svc->set('auth.mfa_enabled', '0', null, $this->admin);
        $this->assertFalse($this->svc->get('auth.mfa_enabled'));
    }

    public function test_validation_rejects_invalid_value()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->set('app.locale', 'fr', null, $this->admin);
    }

    public function test_unknown_key_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->set('hacker.key', 'x', null, $this->admin);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->get('hacker.key');
    }

    public function test_deployment_managed_setting_cannot_be_changed()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->set('app.env', 'local', null, $this->admin);
    }

    public function test_branch_override_precedence()
    {
        $branch = Branch::where('code', 'HQ')->first();
        $this->svc->set('app.date_format', 'Y-m-d', null, $this->admin);
        $this->svc->set('app.date_format', 'm/d/Y', $branch->id, $this->admin);
        $this->assertEquals('m/d/Y', $this->svc->get('app.date_format', $branch->id));
        $this->assertEquals('Y-m-d', $this->svc->get('app.date_format'));
    }

    public function test_global_scoped_setting_rejects_branch_override()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->set('app.locale', 'sw', Branch::first()->id, $this->admin);
    }

    public function test_remove_branch_override_falls_back_to_global()
    {
        $branch = Branch::first();
        $this->svc->set('app.currency', 'USD', null, $this->admin);
        $this->svc->set('app.currency', 'KES', $branch->id, $this->admin);
        $this->svc->removeBranchOverride('app.currency', $branch->id, $this->admin);
        $this->assertEquals('USD', $this->svc->get('app.currency', $branch->id));
    }

    public function test_secret_is_encrypted_at_rest_and_masked()
    {
        $this->svc->set('notify.sms_api_key', 'supersecret123', null, $this->admin);
        $row = Setting::where('key', 'notify.sms_api_key')->first();
        $this->assertStringNotContainsString('supersecret123', $row->value);
        $this->assertEquals('supersecret123', Crypt::decryptString($row->value));
        $grouped = collect($this->svc->groupedValues())->flatMap(fn ($c) => $c['settings'])->firstWhere('key', 'notify.sms_api_key');
        $this->assertEquals('********', $grouped['value']);
        $this->assertStringNotContainsString('supersecret123', json_encode($grouped));
    }

    public function test_empty_secret_input_does_not_overwrite()
    {
        $this->svc->set('notify.sms_api_key', 'realkey', null, $this->admin);
        $this->svc->set('notify.sms_api_key', '', null, $this->admin);
        $this->assertEquals('realkey', $this->svc->get('notify.sms_api_key'));
    }

    public function test_secret_replacement_and_deletion()
    {
        $this->svc->set('notify.sms_api_key', 'first', null, $this->admin);
        $this->svc->set('notify.sms_api_key', 'second', null, $this->admin);
        $this->assertEquals('second', $this->svc->get('notify.sms_api_key'));
        $this->svc->deleteSecret('notify.sms_api_key', null, $this->admin);
        $this->assertNull(Setting::where('key', 'notify.sms_api_key')->first());
    }

    public function test_secrets_never_in_audit_log()
    {
        $this->svc->set('notify.whatsapp_token', 'tok123', null, $this->admin);
        $log = AuditLog::where('action', 'setting.updated')->latest('id')->first();
        $this->assertStringNotContainsString('tok123', json_encode($log->toArray()));
    }

    public function test_unauthorized_user_cannot_update_settings()
    {
        $staff = User::factory()->create();
        $staff->assignRole('Staff');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc->set('app.name', 'Hacked', null, $staff);
    }

    public function test_settings_update_route()
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['app.name' => 'Amoleck Test'])
            ->assertRedirect();
        $this->assertEquals('Amoleck Test', $this->svc->get('app.name'));
    }

    public function test_history_page_accessible()
    {
        $this->actingAs($this->admin)->get(route('admin.settings.history'))->assertOk();
    }
}
