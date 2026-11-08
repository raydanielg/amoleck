<?php
namespace Tests\Feature\HospitalOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HospitalOperations\Models\{EmergencyAcuityCategory, EmergencyEncounter};
use Modules\HospitalOperations\Services\{EmergencyService, EmergencyClinicalService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class EmergencyClinicalTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $clinician; private EmergencyEncounter $enc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->clinician = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        $ac = EmergencyAcuityCategory::create(['code' => 'IMM', 'label' => 'I', 'priority' => 1]);
        $v = app(EmergencyService::class)->arrive(['patient_id' => 1, 'branch_id' => $hq->id], $this->admin->id);
        app(EmergencyService::class)->assess($v, $ac->id, [], $this->admin->id);
        $this->enc = app(EmergencyClinicalService::class)->openEncounter($v->fresh(), $this->clinician->id);
    }

    public function test_encounter_requires_triage()
    {
        $hq = Branch::where('code', 'HQ')->first();
        $v = app(EmergencyService::class)->arrive(['patient_id' => 2, 'branch_id' => $hq->id], $this->admin->id); // not triaged
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(EmergencyClinicalService::class)->openEncounter($v, $this->clinician->id);
    }
    public function test_one_encounter_per_visit() { $e2 = app(EmergencyClinicalService::class)->openEncounter($this->enc->visit, $this->clinician->id); $this->assertEquals(1, EmergencyEncounter::count()); }
    public function test_assess_and_diagnose()
    {
        $svc = app(EmergencyClinicalService::class);
        $a = $svc->assess($this->enc, ['complaint' => 'chest pain', 'examination' => 'stable'], $this->clinician->id);
        $d = $svc->diagnose($this->enc, ['working_diagnosis' => 'ACS r/o'], $this->clinician->id);
        $this->assertEquals('draft', $a->status);
        $this->assertEquals('working', $d->status);
    }
    public function test_plan_approval_self_blocked()
    {
        $svc = app(EmergencyClinicalService::class);
        $p = $svc->createPlan($this->enc, 'IV fluids + monitor', $this->clinician->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->approvePlan($p, $this->clinician->id);
    }
    public function test_intervention_lifecycle()
    {
        $svc = app(EmergencyClinicalService::class);
        $i = $svc->performIntervention($this->enc, 'suturing', 'laceration', $this->clinician->id);
        $svc->completeIntervention($i, $this->clinician->id);
        $this->assertEquals('completed', $i->fresh()->status);
    }
    public function test_referral_numbered()
    {
        $r = app(EmergencyClinicalService::class)->refer($this->enc, 'internal', 'Cardiology', 'chest pain', $this->clinician->id);
        $this->assertMatchesRegularExpression('/^REF-\d{4}-\d{6}$/', $r->referral_number);
    }
}
