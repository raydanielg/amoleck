<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Appointment;
use Modules\HospitalOperations\Models\AppointmentType;
use Modules\HospitalOperations\Models\ClinicianSchedule;
use Modules\HospitalOperations\Models\HospitalResource;
use Modules\HospitalOperations\Services\AppointmentService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $rec; private Branch $hq; private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['hospitaloperations', 'patientcare'])->update(['status' => 'active']);
        $this->rec = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->rec->assignRole('Receptionist');
        $this->rec->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'A', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
    }

    private function book(array $over = []): Appointment
    {
        return app(AppointmentService::class)->create(array_merge([
            'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id,
            'appointment_date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:30',
        ], $over), $this->rec->id);
    }

    public function test_appointment_number_unique()
    {
        $a = $this->book();
        $this->assertMatchesRegularExpression('/^APT-\d{4}-\d{6}$/', $a->appointment_number);
        $this->assertEquals('draft', $a->statusHistories()->first()->to_status);
    }

    public function test_clinician_double_booking_rejected()
    {
        $clin = $this->rec->id;
        $this->book(['clinician_id' => $clin]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->book(['clinician_id' => $clin, 'start_time' => '09:15', 'end_time' => '09:45']);
    }

    public function test_resource_conflict_rejected()
    {
        $res = HospitalResource::create(['code' => 'RM-1', 'name' => 'Room 1', 'branch_id' => $this->hq->id]);
        $this->book(['hospital_resource_id' => $res->id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->book(['hospital_resource_id' => $res->id, 'start_time' => '09:15', 'end_time' => '09:45']);
    }

    public function test_schedule_required_for_booking()
    {
        $type = AppointmentType::create(['code' => 'IC', 'name' => 'Initial', 'default_duration' => 30]);
        $clin = User::factory()->create(['status' => 'active']);
        $this->actingAs($this->admin)->post(route('admin.appointments.store'), [
            'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'appointment_type_id' => $type->id,
            'clinician_id' => $clin->id, 'appointment_date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'duration' => 30,
        ]);
        $this->assertEquals(0, Appointment::count());
        ClinicianSchedule::create(['user_id' => $clin->id, 'branch_id' => $this->hq->id, 'day_of_week' => now()->addDay()->dayOfWeek, 'start_time' => '08:00', 'end_time' => '17:00']);
        $this->actingAs($this->admin)->post(route('admin.appointments.store'), [
            'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'appointment_type_id' => $type->id,
            'clinician_id' => $clin->id, 'appointment_date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'duration' => 30,
        ]);
        $this->assertEquals(1, Appointment::count());
    }

    public function test_status_workflow()
    {
        $a = $this->book();
        $svc = app(AppointmentService::class);
        $svc->transition($a, 'pending_confirmation', $this->admin->id);
        $svc->transition($a, 'confirmed', $this->admin->id);
        $svc->transition($a, 'checked_in', $this->admin->id);
        $this->assertNotNull($a->fresh()->checked_in_at);
        $svc->transition($a, 'in_progress', $this->admin->id);
        $svc->transition($a, 'completed', $this->admin->id);
        $this->assertEquals('completed', $a->fresh()->status);
        $this->assertEquals(6, $a->statusHistories()->count());
    }

    public function test_cancel_requires_reason()
    {
        $a = $this->book();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AppointmentService::class)->transition($a, 'cancelled', $this->admin->id);
    }

    public function test_reschedule_preserves_history()
    {
        $a = $this->book();
        $a->update(['status' => 'confirmed']);
        $new = now()->addDays(2)->toDateString();
        app(AppointmentService::class)->reschedule($a, $new, '14:00', '14:30', $this->admin->id, 'Patient request');
        $a = $a->fresh();
        $this->assertEquals('rescheduled', $a->status);
        $this->assertEquals($new, $a->appointment_date->toDateString());
        $this->assertEquals('Patient request', $a->rescheduleHistories()->first()->reason);
    }

    public function test_invalid_transition_rejected()
    {
        $a = $this->book();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AppointmentService::class)->transition($a, 'completed', $this->admin->id);
    }

    public function test_cross_branch_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $foreign = Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $other->id]);
        $a = Appointment::create(['appointment_number' => 'APT-2026-000090', 'patient_id' => $foreign->id, 'branch_id' => $other->id, 'appointment_date' => now()->addDay(), 'start_time' => '09:00', 'end_time' => '09:30', 'created_by' => $this->admin->id]);
        $this->actingAs($this->rec)->get(route('admin.appointments.show', $a))->assertForbidden();
    }

    public function test_available_slots()
    {
        $d = now()->addDays(1)->toDateString();
        ClinicianSchedule::create(['user_id' => $this->rec->id, 'branch_id' => $this->hq->id, 'day_of_week' => now()->addDay()->dayOfWeek, 'start_time' => '09:00', 'end_time' => '11:00', 'break_start' => '10:00', 'break_end' => '10:30']);
        $slots = app(AppointmentService::class)->availableSlots($this->rec->id, $this->hq->id, $d);
        $starts = collect($slots)->pluck('start');
        $this->assertTrue($starts->contains('09:00'));
        $this->assertFalse($starts->contains('10:00'));
    }
}
