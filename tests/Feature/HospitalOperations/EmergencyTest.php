<?php
namespace Tests\Feature\HospitalOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HospitalOperations\Models\{EmergencyAcuityCategory, EmergencyVisit};
use Modules\HospitalOperations\Services\EmergencyService;
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class EmergencyTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $nurse; private Branch $hq; private EmergencyAcuityCategory $acuity; private EmergencyVisit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->nurse = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        $this->acuity = EmergencyAcuityCategory::create(['code' => 'IMM', 'label' => 'Immediate', 'priority' => 1]);
        $this->visit = app(EmergencyService::class)->arrive(['patient_id' => 1, 'branch_id' => $this->hq->id, 'arrival_mode' => 'ambulance', 'presenting_complaint' => 'chest pain'], $this->admin->id);
    }

    public function test_visit_numbered() { $this->assertMatchesRegularExpression('/^ER-\d{4}-\d{6}$/', $this->visit->visit_number); }
    public function test_temp_patient_numbered()
    {
        $v = app(EmergencyService::class)->arrive(['unidentified' => true, 'branch_id' => $this->hq->id, 'arrival_mode' => 'ambulance'], $this->admin->id);
        $this->assertMatchesRegularExpression('/^TEMP-\d{4}-\d{6}$/', $v->temp_patient_number);
        $this->assertNull($v->patient_id);
    }
    public function test_patient_or_flag_required() { $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(EmergencyService::class)->arrive(['branch_id' => $this->hq->id], $this->admin->id); }
    public function test_triage() { $a = app(EmergencyService::class)->assess($this->visit, $this->acuity->id, ['complaint' => 'chest pain'], $this->nurse->id); $this->assertEquals('triaged', $this->visit->fresh()->status); $this->assertEquals($this->acuity->id, $a->emergency_acuity_category_id); }
    public function test_reassessment_preserves_history()
    {
        $svc = app(EmergencyService::class);
        $svc->assess($this->visit, $this->acuity->id, ['complaint' => 'initial'], $this->nurse->id);
        $svc->reassess($this->visit->fresh(), $this->acuity->id, ['complaint' => 'worsened'], $this->nurse->id);
        $this->assertEquals(2, $this->visit->assessments()->count()); // both preserved
    }
    public function test_alert_lifecycle()
    {
        $svc = app(EmergencyService::class);
        $a = $svc->alert($this->visit, 'deteriorating', $this->nurse->id);
        $svc->acknowledgeAlert($a, $this->admin->id);
        $this->assertEquals('acknowledged', $a->fresh()->status);
    }
    public function test_handover_receiver_only_ack()
    {
        $svc = app(EmergencyService::class);
        $h = $svc->handover($this->visit, $this->nurse->id, 'Transfer to ward', $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->acknowledgeHandover($h, $this->admin->id); // admin isn't the receiver
    }
    public function test_handover_ack()
    {
        $svc = app(EmergencyService::class);
        $h = $svc->handover($this->visit, $this->nurse->id, 'x', $this->admin->id);
        $svc->acknowledgeHandover($h, $this->nurse->id);
        $this->assertEquals('acknowledged', $h->fresh()->status);
    }
    public function test_dashboard()
    {
        app(EmergencyService::class)->assess($this->visit, $this->acuity->id, [], $this->nurse->id);
        $d = app(EmergencyService::class)->dashboard($this->hq->id);
        $this->assertEquals(0, $d['arrived']);
        $this->assertEquals(1, $d['triaged']);
    }
}
