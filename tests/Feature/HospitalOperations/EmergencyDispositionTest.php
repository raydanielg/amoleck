<?php
namespace Tests\Feature\HospitalOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HospitalOperations\Models\{EmergencyAcuityCategory, EmergencyVisit};
use Modules\HospitalOperations\Services\{EmergencyService, EmergencyDispositionService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class EmergencyDispositionTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $approver; private EmergencyVisit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        $ac = EmergencyAcuityCategory::create(['code' => 'IMM', 'label' => 'I', 'priority' => 1]);
        $this->visit = app(EmergencyService::class)->arrive(['patient_id' => 1, 'branch_id' => $hq->id], $this->admin->id);
        app(EmergencyService::class)->assess($this->visit, $ac->id, [], $this->admin->id);
    }

    public function test_disposition_workflow()
    {
        $svc = app(EmergencyDispositionService::class);
        $d = $svc->propose($this->visit->fresh(), 'admit', 'Needs admission', $this->admin->id);
        $svc->approve($d, $this->approver->id);
        $this->assertEquals('approved', $d->fresh()->status);
    }
    public function test_self_disposition_rejected()
    {
        $svc = app(EmergencyDispositionService::class);
        $d = $svc->propose($this->visit->fresh(), 'admit', 'x', $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->approve($d, $this->admin->id);
    }
    public function test_discharge_lifecycle()
    {
        $svc = app(EmergencyDispositionService::class);
        $d = $svc->discharge($this->visit->fresh(), ['type' => 'home', 'summary' => 'Recovered'], $this->admin->id);
        $this->assertMatchesRegularExpression('/^DIS-\d{4}-\d{6}$/', $d->discharge_number);
        $svc->authorizeDischarge($d, $this->approver->id);
        $this->assertEquals('authorized', $d->fresh()->status);
        $this->assertEquals('discharged', $this->visit->fresh()->status);
    }
    public function test_transfer_receive()
    {
        $svc = app(EmergencyDispositionService::class);
        $t = $svc->transfer($this->visit->fresh(), ['type' => 'internal', 'destination' => 'Ward A'], $this->admin->id);
        $this->assertMatchesRegularExpression('/^TRN-\d{4}-\d{6}$/', $t->transfer_number);
        $svc->receiveTransfer($t, $this->approver->id);
        $this->assertEquals('received', $t->fresh()->status);
    }
}
