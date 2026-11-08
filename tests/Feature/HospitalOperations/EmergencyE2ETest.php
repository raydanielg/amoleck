<?php
namespace Tests\Feature\HospitalOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HospitalOperations\Models\{EmergencyAcuityCategory, EmergencyVisit};
use Modules\HospitalOperations\Services\{EmergencyService, EmergencyClinicalService, EmergencyDispositionService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class EmergencyE2ETest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $clinician; private User $approver; private Branch $hq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->clinician = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
    }

    /** arrive→triage→reassess→encounter→assess→diagnose→plan→intervention→discharge. */
    public function test_full_emergency_journey()
    {
        $er = app(EmergencyService::class); $clin = app(EmergencyClinicalService::class); $disp = app(EmergencyDispositionService::class);
        $ac = EmergencyAcuityCategory::create(['code' => 'URG', 'label' => 'Urgent', 'priority' => 2]);
        $ac2 = EmergencyAcuityCategory::create(['code' => 'IMM', 'label' => 'Immediate', 'priority' => 1]);

        $v = $er->arrive(['patient_id' => 1, 'branch_id' => $this->hq->id, 'arrival_mode' => 'ambulance', 'presenting_complaint' => 'SOB'], $this->admin->id);
        $this->assertMatchesRegularExpression('/^ER-\d{4}-\d{6}$/', $v->visit_number);
        $er->assess($v, $ac->id, ['complaint' => 'SOB'], $this->admin->id);
        $er->reassess($v->fresh(), $ac2->id, ['complaint' => 'worsened'], $this->admin->id); // escalation — 2 assessments preserved
        $this->assertEquals(2, $v->assessments()->count());

        $e = $clin->openEncounter($v->fresh(), $this->clinician->id);
        $clin->assess($e, ['complaint' => 'SOB', 'examination' => 'crackles'], $this->clinician->id);
        $clin->diagnose($e, ['working_diagnosis' => 'Pulmonary edema'], $this->clinician->id);
        $p = $clin->createPlan($e, 'O2 + monitor', $this->clinician->id);
        $clin->approvePlan($p, $this->approver->id);
        $i = $clin->performIntervention($e, 'oxygen', 'nasal prongs', $this->clinician->id);
        $clin->completeIntervention($i, $this->clinician->id);

        $d = $disp->propose($v->fresh(), 'discharge', 'Improved', $this->clinician->id);
        $disp->approve($d, $this->approver->id);
        $disc = $disp->discharge($v->fresh(), ['type' => 'home', 'summary' => 'Resolved'], $this->clinician->id);
        $disp->authorizeDischarge($disc, $this->approver->id);
        $this->assertEquals('discharged', $v->fresh()->status);

        // dashboard
        $d2 = $er->dashboard($this->hq->id);
        $this->assertEquals(0, $d2['arrived']); // all discharged
    }
}
