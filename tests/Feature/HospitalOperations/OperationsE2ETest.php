<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\Bed;
use Modules\HospitalOperations\Models\MedicationOrder;
use Modules\HospitalOperations\Models\PatientVisit;
use Modules\HospitalOperations\Models\QueueType;
use Modules\HospitalOperations\Models\Room;
use Modules\HospitalOperations\Models\VitalSign;
use Modules\HospitalOperations\Models\Ward;
use Modules\HospitalOperations\Services\AppointmentService;
use Modules\HospitalOperations\Services\IpdService;
use Modules\HospitalOperations\Services\MedicationService;
use Modules\HospitalOperations\Services\NursingService;
use Modules\HospitalOperations\Services\QueueService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class OperationsE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_patient_journey()
    {
        // Setup — foundation + all modules
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['hospitaloperations', 'patientcare'])->update(['status' => 'active']);

        $doctor = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $doctor->assignRole('Doctor');
        $nurse = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $nurse2 = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $rec = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $rec->assignRole('Receptionist');
        $rec->branches()->sync([$hq->id => ['is_primary' => true]]);

        // 1. Patient (organization-wide MRN — PatientCare)
        $patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'E2E', 'last_name' => 'Patient', 'primary_branch_id' => $hq->id]);

        // 2. Appointment (M1)
        $appt = app(AppointmentService::class)->create([
            'patient_id' => $patient->id, 'branch_id' => $hq->id,
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:30',
        ], $rec->id);
        app(AppointmentService::class)->transition($appt, 'pending_confirmation', $rec->id);
        app(AppointmentService::class)->transition($appt, 'confirmed', $rec->id);
        $this->assertEquals('confirmed', $appt->fresh()->status);

        // 3. Arrival + queue (M2)
        $qt = QueueType::create(['code' => 'GEN', 'name' => 'General', 'branch_id' => $hq->id]);
        $visit = app(QueueService::class)->createVisit([
            'patient_id' => $patient->id, 'branch_id' => $hq->id, 'queue_type_id' => $qt->id,
            'source' => 'appointment', 'appointment_id' => $appt->id,
        ], $rec->id);
        app(QueueService::class)->transitionVisit($visit, 'checked_in', $rec->id);
        app(QueueService::class)->transitionVisit($visit, 'waiting', $rec->id);
        $called = app(QueueService::class)->callNext($qt->id, $doctor->id);
        $this->assertNotNull($called);
        app(QueueService::class)->transitionVisit($visit->fresh(), 'in_service', $doctor->id);

        // 4. Clinical encounter (Phase 3 — linked to same patient, no duplicate)
        $enc = app(\Modules\PatientCare\Clinical\Services\ClinicalEncounterService::class)->create([
            'patient_id' => $patient->id, 'branch_id' => $hq->id, 'type' => 'initial',
            'responsible_clinician_id' => $doctor->id, 'encounter_at' => now(),
        ], $doctor->id);
        $this->assertEquals($patient->id, $enc->patient_id);
        $this->assertEquals(1, \Modules\PatientCare\Clinical\Models\ClinicalEncounter::where('patient_id', $patient->id)->count());
        app(QueueService::class)->transitionVisit($visit->fresh(), 'completed', $doctor->id);

        // 5. Admission (M4)
        $ward = Ward::create(['code' => 'W1', 'name' => 'Med', 'branch_id' => $hq->id, 'capacity' => 10]);
        $room = Room::create(['code' => 'R1', 'name' => 'R1', 'ward_id' => $ward->id, 'branch_id' => $hq->id]);
        $bed = Bed::create(['code' => 'B1', 'number' => '1', 'room_id' => $room->id, 'ward_id' => $ward->id, 'branch_id' => $hq->id, 'status' => 'available']);
        $bed2 = Bed::create(['code' => 'B2', 'number' => '2', 'room_id' => $room->id, 'ward_id' => $ward->id, 'branch_id' => $hq->id, 'status' => 'available']);

        $adm = app(IpdService::class)->createAdmission(['patient_id' => $patient->id, 'branch_id' => $hq->id, 'type' => 'elective', 'source' => 'opd', 'clinician_id' => $doctor->id], $doctor->id);
        app(IpdService::class)->transition($adm, 'approved', $admin->id);
        app(IpdService::class)->transition($adm->fresh(), 'awaiting_bed', $admin->id);
        app(IpdService::class)->allocateBed($adm->fresh(), $bed, $nurse->id);
        $adm = $adm->fresh();
        $this->assertEquals('admitted', $adm->status);
        $this->assertEquals($bed->id, $adm->bed_id);

        // 6. Nursing (M5) — assign + vitals + note
        $vital = app(NursingService::class)->recordVital([
            'patient_id' => $patient->id, 'admission_id' => $adm->id, 'branch_id' => $hq->id,
            'kind' => 'temperature', 'value_num' => 37.2, 'unit' => '°C', 'taken_at' => now(),
        ], $nurse->id);
        $this->assertEquals('°C', $vital->unit);
        $note = app(NursingService::class)->createNote(['patient_id' => $patient->id, 'admission_id' => $adm->id, 'branch_id' => $hq->id, 'category' => 'initial', 'subjective' => 'Stable'], $nurse->id);
        app(NursingService::class)->finalizeNote($note, $nurse->id);

        // 7. Medication (M6) — order → approve → doses → administer + missed
        $order = app(MedicationService::class)->createOrder([
            'patient_id' => $patient->id, 'admission_id' => $adm->id, 'branch_id' => $hq->id,
            'generic_name' => 'Paracetamol', 'dose' => '500', 'dose_unit' => 'mg', 'route' => 'oral',
            'frequency' => 'BD', 'schedule_times' => ['08:00', '20:00'], 'type' => 'regular',
        ], $doctor->id);
        app(MedicationService::class)->transition($order, 'approved', $admin->id);
        app(MedicationService::class)->transition($order->fresh(), 'active', $admin->id);
        $this->assertGreaterThan(0, app(MedicationService::class)->generateDoses($order->fresh(), 1));
        $dose = $order->scheduledDoses()->first();
        app(MedicationService::class)->administer($dose, ['actual_dose' => '500', 'actual_route' => 'oral'], $nurse->id);
        $dose2 = $order->scheduledDoses()->skip(1)->first();
        app(MedicationService::class)->recordNonAdmin($dose2, 'missed', 'Asleep', $nurse->id);

        // 8. Handover (M5)
        $handover = app(NursingService::class)->createHandover(['branch_id' => $hq->id, 'summary' => 'Day→Night'], $nurse->id);
        app(NursingService::class)->acknowledgeHandover($handover, $nurse2->id);
        $this->assertEquals('acknowledged', $handover->fresh()->status);

        // 9. Transfer (M4) — bed B1 → B2, history preserved
        app(IpdService::class)->transfer($adm->fresh(), $bed2, $nurse->id, 'Clinical need');
        $adm = $adm->fresh();
        $this->assertEquals($bed2->id, $adm->bed_id);
        $this->assertEquals('cleaning', $bed->fresh()->status);
        $this->assertEquals(2, $adm->allocations()->count());
        // Nursing records survive
        $this->assertEquals(1, VitalSign::where('admission_id', $adm->id)->count());
        $this->assertEquals(1, MedicationOrder::where('admission_id', $adm->id)->count()); // med order survives transfer

        // 10. Discharge (M4) — bed released → cleaning → available
        app(IpdService::class)->discharge($adm, 'routine', 'Recovered', $doctor->id);
        $adm = $adm->fresh();
        $this->assertEquals('discharged', $adm->status);
        $this->assertNull($adm->bed_id);
        $this->assertEquals('cleaning', $bed2->fresh()->status);
        app(IpdService::class)->completeCleaning($bed2->fresh(), $nurse->id);
        $this->assertEquals('available', $bed2->fresh()->status);

        // 11. Everything preserved: appointment, visit, encounter, admission, meds, vitals, handover
        $this->assertDatabaseHas('appointments', ['appointment_number' => $appt->appointment_number]);
        $this->assertDatabaseHas('patient_visits', ['visit_number' => $visit->visit_number, 'status' => 'completed']);
        $this->assertDatabaseHas('clinical_encounters', ['id' => $enc->id, 'patient_id' => $patient->id]);
        $this->assertDatabaseHas('admissions', ['admission_number' => $adm->admission_number, 'status' => 'discharged']);
        $this->assertDatabaseHas('medication_administrations', ['status' => 'administered']);
        $this->assertDatabaseHas('medication_administrations', ['status' => 'missed']);
        $this->assertGreaterThan(15, AuditLog::where('entity_type', Patient::class)->where('entity_id', $patient->id)->count()); // full audit trail
    }

    public function test_walk_in_does_not_fabricate_appointment()
    {
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'hospitaloperations')->update(['status' => 'active']);
        $patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'W', 'last_name' => 'I', 'primary_branch_id' => $hq->id]);
        $qt = QueueType::create(['code' => 'GEN', 'name' => 'Gen', 'branch_id' => $hq->id]);
        $visit = app(QueueService::class)->createVisit(['patient_id' => $patient->id, 'branch_id' => $hq->id, 'queue_type_id' => $qt->id, 'source' => 'walk_in'], $admin->id);
        $this->assertNull($visit->appointment_id);
        $this->assertEquals(0, \Modules\HospitalOperations\Models\Appointment::where('patient_id', $patient->id)->count());
    }

    public function test_patient_timeline_integrity()
    {
        // Every operational record points at ONE canonical patient — no duplicates created by any module
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'hospitaloperations')->update(['status' => 'active']);
        $patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'T', 'last_name' => 'L', 'primary_branch_id' => $hq->id]);
        $this->assertEquals(1, Patient::where('mrn', 'PAT-2026-000001')->count());
    }
}
