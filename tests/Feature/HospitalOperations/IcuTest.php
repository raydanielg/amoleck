<?php
namespace Tests\Feature\HospitalOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\HospitalOperations\Services\IcuService;
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class IcuTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $approver; private int $unitId; private int $bedId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $hq = Branch::where('code', 'HQ')->first();
        $this->unitId = DB::table('icu_units')->insertGetId(['code' => 'MICU', 'name' => 'Medical ICU', 'branch_id' => $hq->id, 'capacity' => 2, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->bedId = DB::table('icu_beds')->insertGetId(['icu_unit_id' => $this->unitId, 'status' => 'available', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_admission_workflow()
    {
        $svc = app(IcuService::class);
        $r = $svc->requestAdmission(1, $this->unitId, 'urgent', $this->admin->id);
        $this->assertMatchesRegularExpression('/^ICU-\d{4}-\d{6}$/', $r->request_number);
        $svc->approve($r, $this->approver->id);
        $stay = $svc->admit(DB::table('icu_admission_requests')->find($r->id), $this->bedId, $this->admin->id);
        $this->assertEquals('active', $stay->status);
        $this->assertEquals('occupied', DB::table('icu_beds')->find($this->bedId)->status);
    }
    public function test_self_approval_rejected()
    {
        $r = app(IcuService::class)->requestAdmission(1, $this->unitId, 'planned', $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(IcuService::class)->approve($r, $this->admin->id);
    }
    public function test_occupied_bed_rejected()
    {
        $svc = app(IcuService::class);
        $r = $svc->requestAdmission(1, $this->unitId, 'urgent', $this->admin->id);
        $r = $svc->approve($r, $this->approver->id);
        $svc->admit($r, $this->bedId, $this->admin->id);
        $r2 = $svc->requestAdmission(2, $this->unitId, 'urgent', $this->admin->id);
        $r2 = $svc->approve($r2, $this->approver->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->admit($r2, $this->bedId, $this->admin->id); // same bed — occupied
    }
    public function test_observations_and_fluid()
    {
        $svc = app(IcuService::class);
        $r = $svc->requestAdmission(1, $this->unitId, 'urgent', $this->admin->id);
        $r = $svc->approve($r, $this->approver->id);
        $stay = $svc->admit($r, $this->bedId, $this->admin->id);
        $svc->observe($stay->id, 'heart_rate', 88, 'bpm', $this->admin->id);
        $svc->observe($stay->id, 'spo2', 94, '%', $this->admin->id);
        $svc->fluid($stay->id, 'in', 'iv', 500, $this->admin->id);
        $svc->fluid($stay->id, 'out', 'urine', 200, $this->admin->id);
        $this->assertEquals(300, $svc->balance($stay->id));
        $this->assertEquals(2, DB::table('icu_observations')->count());
    }
    public function test_discharge_releases_bed()
    {
        $svc = app(IcuService::class);
        $r = $svc->requestAdmission(1, $this->unitId, 'urgent', $this->admin->id);
        $r = $svc->approve($r, $this->approver->id);
        $stay = $svc->admit($r, $this->bedId, $this->admin->id);
        $svc->discharge($stay->id, $this->admin->id);
        $this->assertEquals('cleaning', DB::table('icu_beds')->find($this->bedId)->status);
        $this->assertEquals('discharged', DB::table('icu_stays')->find($stay->id)->status);
    }
}
