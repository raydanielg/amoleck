<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\MedicationAdministration;
use Modules\HospitalOperations\Models\MedicationCatalogue;
use Modules\HospitalOperations\Models\MedicationOrder;
use Modules\HospitalOperations\Models\MedicationScheduledDose;
use Modules\HospitalOperations\Services\MedicationService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class EmarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $prescriber; private User $nurse; private Branch $hq;
    private Patient $patient; private Admission $adm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'hospitaloperations')->update(['status' => 'active']);
        $this->prescriber = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->prescriber->assignRole('Doctor');
        $this->nurse = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'M', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
        $this->adm = Admission::create(['admission_number' => 'ADM-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'elective', 'source' => 'opd', 'status' => 'admitted', 'created_by' => $this->admin->id]);
    }

    private function order(array $over = []): MedicationOrder
    {
        return app(MedicationService::class)->createOrder(array_merge([
            'patient_id' => $this->patient->id, 'admission_id' => $this->adm->id, 'branch_id' => $this->hq->id,
            'generic_name' => 'Paracetamol', 'dose' => '500', 'dose_unit' => 'mg', 'route' => 'oral',
            'frequency' => 'TDS', 'schedule_times' => ['08:00', '14:00', '20:00'], 'type' => 'regular',
        ], $over), $this->prescriber->id);
    }

    public function test_order_created_with_number_and_version()
    {
        $o = $this->order();
        $this->assertMatchesRegularExpression('/^MED-\d{4}-\d{6}$/', $o->order_number);
        $this->assertEquals('draft', $o->status);
        $this->assertEquals(1, $o->versions()->count());
    }

    public function test_order_approval_workflow()
    {
        $o = $this->order();
        $svc = app(MedicationService::class);
        $svc->transition($o, 'pending_review', $this->prescriber->id);
        $svc->transition($o, 'approved', $this->admin->id);
        $this->assertEquals('approved', $o->fresh()->status);
        $this->assertEquals($this->admin->id, $o->fresh()->approved_by);
        $this->assertEquals(3, $o->versions()->count());
    }

    public function test_self_approval_rejected()
    {
        $o = $this->order();
        $this->actingAs($this->prescriber)->post(route('admin.emar.orders.transition', $o), ['status' => 'approved']);
        $this->assertNotEquals('approved', $o->fresh()->status);
    }

    public function test_dose_generation_and_administration()
    {
        $o = $this->order();
        $o->update(['status' => 'active']);
        $svc = app(MedicationService::class);
        $this->assertEquals(9, $svc->generateDoses($o, 3)); // 3 times/day × 3 days
        $dose = $o->scheduledDoses()->first();
        $admin = $svc->administer($dose, ['actual_dose' => '500', 'actual_route' => 'oral'], $this->nurse->id);
        $this->assertEquals('administered', $admin->status);
        $this->assertEquals($this->nurse->id, $admin->administered_by);
    }

    public function test_double_administration_rejected()
    {
        $o = $this->order();
        $o->update(['status' => 'active']);
        $svc = app(MedicationService::class);
        $svc->generateDoses($o, 1);
        $dose = $o->scheduledDoses()->first();
        $svc->administer($dose, ['actual_dose' => '500', 'actual_route' => 'oral'], $this->nurse->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->administer($dose->fresh(), ['actual_dose' => '500', 'actual_route' => 'oral'], $this->nurse->id);
    }

    public function test_missed_dose_preserved()
    {
        $o = $this->order();
        $o->update(['status' => 'active']);
        $svc = app(MedicationService::class);
        $svc->generateDoses($o, 1);
        $dose = $o->scheduledDoses()->first();
        $svc->recordNonAdmin($dose, 'missed', 'Patient asleep', $this->nurse->id);
        $this->assertEquals('missed', $dose->fresh()->status);
        $this->assertEquals('Patient asleep', MedicationAdministration::first()->reason);
    }

    public function test_prn_interval_enforced()
    {
        $o = $this->order(['type' => 'prn', 'prn_min_interval_minutes' => 240]);
        $o->update(['status' => 'active']);
        $svc = app(MedicationService::class);
        $svc->administerPrn($o, ['actual_dose' => '500', 'actual_route' => 'oral'], $this->nurse->id, 'Pain');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->administerPrn($o, ['actual_dose' => '500', 'actual_route' => 'oral'], $this->nurse->id, 'Still pain');
    }

    public function test_discontinue_cancels_pending_doses()
    {
        $o = $this->order();
        $o->update(['status' => 'active']);
        $svc = app(MedicationService::class);
        $svc->generateDoses($o, 1);
        $svc->transition($o->fresh(), 'discontinued', $this->admin->id, 'Adverse reaction');
        $this->assertEquals('discontinued', $o->fresh()->status);
        $this->assertEquals(0, $o->scheduledDoses()->where('status', 'scheduled')->count()); // all cancelled
        $this->assertEquals('Adverse reaction', $o->fresh()->discontinue_reason);
    }

    public function test_catalogue_creation()
    {
        $this->actingAs($this->admin)->post(route('admin.emar.catalogue.store'), ['code' => 'PCM500', 'generic_name' => 'Paracetamol', 'dosage_form' => 'tablet']);
        $this->assertEquals(1, MedicationCatalogue::count());
    }

    public function test_cross_branch_history_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$other->id => ['is_primary' => true]]);
        $this->actingAs($staff)->get(route('admin.emar.history', $this->patient))->assertForbidden();
    }
}
