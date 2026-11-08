<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\NursingNote;
use Modules\HospitalOperations\Models\VitalSign;
use Modules\HospitalOperations\Services\NursingService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class NursingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Branch $hq; private Patient $patient; private Admission $adm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'hospitaloperations')->update(['status' => 'active']);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'N', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
        $this->adm = Admission::create(['admission_number' => 'ADM-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'elective', 'source' => 'opd', 'status' => 'admitted', 'created_by' => $this->admin->id]);
    }

    public function test_vital_recorded_and_audited()
    {
        $v = app(NursingService::class)->recordVital(['patient_id' => $this->patient->id, 'admission_id' => $this->adm->id, 'branch_id' => $this->hq->id, 'kind' => 'temperature', 'value_num' => 37.5, 'unit' => '°C', 'taken_at' => now()], $this->admin->id);
        $this->assertEquals('°C', $v->unit);
        $this->assertEquals('nursing.vital_recorded', AuditLog::latest('id')->first()->action);
    }

    public function test_wrong_unit_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NursingService::class)->recordVital(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'kind' => 'temperature', 'value_num' => 37, 'unit' => '°F', 'taken_at' => now()], $this->admin->id);
    }

    public function test_vital_correction_preserves_original()
    {
        $svc = app(NursingService::class);
        $v = $svc->recordVital(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'kind' => 'pulse', 'value_num' => 72, 'unit' => 'bpm', 'taken_at' => now()], $this->admin->id);
        $new = $svc->correctVital($v, ['value_num' => 74, 'unit' => 'bpm', 'taken_at' => now()], $this->admin->id, 'Typo');
        $this->assertEquals(74, $new->value_num);
        $this->assertEquals('Typo', $v->fresh()->correction_reason);
        $this->assertEquals(2, VitalSign::count()); // original preserved
    }

    public function test_note_finalize_and_amend()
    {
        $svc = app(NursingService::class);
        $n = $svc->createNote(['patient_id' => $this->patient->id, 'admission_id' => $this->adm->id, 'branch_id' => $this->hq->id, 'category' => 'routine', 'subjective' => 'Pt reports pain'], $this->admin->id);
        $svc->finalizeNote($n, $this->admin->id);
        $this->assertEquals('finalized', $n->fresh()->status);
        $svc->amendNote($n, ['subjective' => 'Corrected'], $this->admin->id, 'Clarification');
        $this->assertEquals(2, $n->versions()->count());
        $this->assertEquals('Clarification', $n->versions()->latest('version')->first()->reason);
    }

    public function test_unfinalized_note_rejects_amend()
    {
        $n = app(NursingService::class)->createNote(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'category' => 'routine'], $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NursingService::class)->amendNote($n, [], $this->admin->id, 'x');
    }

    public function test_fluid_balance_net()
    {
        \Modules\HospitalOperations\Models\FluidBalanceEntry::create(['patient_id' => $this->patient->id, 'admission_id' => $this->adm->id, 'direction' => 'intake', 'category' => 'oral', 'amount' => 500, 'recorded_at' => now(), 'recorded_by' => $this->admin->id]);
        \Modules\HospitalOperations\Models\FluidBalanceEntry::create(['patient_id' => $this->patient->id, 'admission_id' => $this->adm->id, 'direction' => 'output', 'category' => 'urine', 'amount' => 300, 'recorded_at' => now(), 'recorded_by' => $this->admin->id]);
        $b = app(NursingService::class)->fluidBalance($this->adm->id);
        $this->assertEquals(500, $b['intake']);
        $this->assertEquals(300, $b['output']);
        $this->assertEquals(200, $b['net']);
    }

    public function test_escalation_acknowledgement()
    {
        $e = app(NursingService::class)->escalate(['patient_id' => $this->patient->id, 'admission_id' => $this->adm->id, 'branch_id' => $this->hq->id, 'concern' => 'BP rising'], $this->admin->id);
        $this->assertEquals('open', $e->status);
        app(NursingService::class)->acknowledgeEscalation($e, $this->admin->id);
        $this->assertEquals('acknowledged', $e->fresh()->status);
        $this->assertNotNull($e->fresh()->acknowledged_at);
    }

    public function test_handover_requires_different_acknowledger()
    {
        $h = app(NursingService::class)->createHandover(['branch_id' => $this->hq->id, 'summary' => 'Shift A→B'], $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NursingService::class)->acknowledgeHandover($h, $this->admin->id); // same person
    }

    public function test_cross_branch_vital_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$other->id => ['is_primary' => true]]);
        $this->actingAs($staff)->post(route('admin.nursing.vitals.store'), ['patient_id' => $this->patient->id, 'kind' => 'temperature', 'value_num' => 37, 'taken_at' => now()])->assertForbidden();
    }
}
