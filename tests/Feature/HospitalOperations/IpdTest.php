<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\Bed;
use Modules\HospitalOperations\Models\Room;
use Modules\HospitalOperations\Models\Ward;
use Modules\HospitalOperations\Services\IpdService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class IpdTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Branch $hq; private Patient $patient; private Bed $bed; private Bed $bed2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'hospitaloperations')->update(['status' => 'active']);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'I', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
        $ward = Ward::create(['code' => 'W1', 'name' => 'Ward 1', 'branch_id' => $this->hq->id, 'capacity' => 10]);
        $room = Room::create(['code' => 'R1', 'name' => 'Room 1', 'ward_id' => $ward->id, 'branch_id' => $this->hq->id]);
        $this->bed = Bed::create(['code' => 'B1', 'number' => '1', 'room_id' => $room->id, 'ward_id' => $ward->id, 'branch_id' => $this->hq->id, 'status' => 'available']);
        $this->bed2 = Bed::create(['code' => 'B2', 'number' => '2', 'room_id' => $room->id, 'ward_id' => $ward->id, 'branch_id' => $this->hq->id, 'status' => 'available']);
    }

    private function admission(string $status = 'approved'): Admission
    {
        $a = app(IpdService::class)->createAdmission(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'elective', 'source' => 'opd'], $this->admin->id);
        if ($status !== 'draft') { $a->update(['status' => $status]); }
        return $a;
    }

    public function test_admission_number_and_workflow()
    {
        $a = app(IpdService::class)->createAdmission(['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'emergency', 'source' => 'opd'], $this->admin->id);
        $this->assertMatchesRegularExpression('/^ADM-\d{4}-\d{6}$/', $a->admission_number);
        $svc = app(IpdService::class);
        $svc->transition($a, 'pending_approval', $this->admin->id);
        $svc->transition($a, 'approved', $this->admin->id);
        $svc->transition($a, 'awaiting_bed', $this->admin->id);
        $this->assertEquals('awaiting_bed', $a->fresh()->status);
        $this->assertEquals(4, $a->statusHistories()->count());
    }

    public function test_bed_allocation_atomic()
    {
        $a = $this->admission('awaiting_bed');
        app(IpdService::class)->allocateBed($a, $this->bed, $this->admin->id);
        $a = $a->fresh();
        $this->assertEquals('admitted', $a->status);
        $this->assertEquals($this->bed->id, $a->bed_id);
        $this->assertEquals('occupied', $this->bed->fresh()->status);
        $this->assertNotNull($a->admitted_at);
    }

    public function test_double_allocation_rejected()
    {
        $a1 = $this->admission('awaiting_bed');
        $a2 = $this->admission('awaiting_bed');
        app(IpdService::class)->allocateBed($a1, $this->bed, $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(IpdService::class)->allocateBed($a2, $this->bed, $this->admin->id);
    }

    public function test_transfer_releases_source_bed_to_cleaning()
    {
        $a = $this->admission('awaiting_bed');
        app(IpdService::class)->allocateBed($a, $this->bed, $this->admin->id);
        app(IpdService::class)->transfer($a->fresh(), $this->bed2, $this->admin->id, 'Upgrade');
        $a = $a->fresh();
        $this->assertEquals($this->bed2->id, $a->bed_id);
        $this->assertEquals('cleaning', $this->bed->fresh()->status);
        $this->assertEquals('occupied', $this->bed2->fresh()->status);
        $this->assertEquals(1, $a->transfers()->count());
        $this->assertEquals(2, $a->allocations()->count()); // old released + new
    }

    public function test_discharge_releases_bed_and_cleans()
    {
        $a = $this->admission('awaiting_bed');
        app(IpdService::class)->allocateBed($a, $this->bed, $this->admin->id);
        app(IpdService::class)->discharge($a->fresh(), 'routine', 'Recovered', $this->admin->id);
        $a = $a->fresh();
        $this->assertEquals('discharged', $a->status);
        $this->assertNull($a->bed_id);
        $this->assertEquals('cleaning', $this->bed->fresh()->status);
        app(IpdService::class)->completeCleaning($this->bed->fresh(), $this->admin->id);
        $this->assertEquals('available', $this->bed->fresh()->status);
    }

    public function test_census_counts()
    {
        $a = $this->admission('awaiting_bed');
        app(IpdService::class)->allocateBed($a, $this->bed, $this->admin->id);
        $c = app(IpdService::class)->census($this->hq->id);
        $this->assertEquals(2, $c['total']);
        $this->assertEquals(1, $c['occupied']);
        $this->assertEquals(1, $c['admitted']);
    }

    public function test_cross_branch_admission_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$other->id => ['is_primary' => true]]);
        $this->actingAs($staff)->post(route('admin.ipd.admissions.store'), ['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'type' => 'elective', 'source' => 'opd'])->assertForbidden();
    }

    public function test_invalid_transition_rejected()
    {
        $a = $this->admission('draft');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(IpdService::class)->transition($a, 'discharged', $this->admin->id);
    }
}
