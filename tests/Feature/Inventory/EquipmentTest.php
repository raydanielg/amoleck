<?php
namespace Tests\Feature\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\{Equipment, EquipmentCategory};
use Modules\Inventory\Services\EquipmentService;
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class EquipmentTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $approver; private Branch $hq; private EquipmentCategory $cat; private Equipment $eqp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Inventory')?->enable();
        $this->cat = EquipmentCategory::create(['code' => 'TENS', 'name' => 'TENS', 'requires_calibration' => true]);
        $this->eqp = app(EquipmentService::class)->register(['name' => 'TENS Unit 1', 'equipment_category_id' => $this->cat->id, 'serial_number' => 'SN-1', 'branch_id' => $this->hq->id], $this->admin->id);
    }

    public function test_equipment_numbered() { $this->assertMatchesRegularExpression('/^EQP-\d{4}-\d{6}$/', $this->eqp->equipment_number); }

    public function test_transfer_and_approve()
    {
        $dst = Branch::create(['code' => 'B2', 'name' => 'B2', 'status' => 'active']);
        $svc = app(EquipmentService::class);
        $t = $svc->transfer($this->eqp, $dst->id, $this->admin->id);
        $svc->approveTransfer($t, $this->approver->id);
        $this->assertEquals($dst->id, $this->eqp->fresh()->branch_id);
    }

    public function test_duplicate_transfer_blocked()
    {
        $dst = Branch::create(['code' => 'B2', 'name' => 'B2', 'status' => 'active']);
        $svc = app(EquipmentService::class);
        $svc->transfer($this->eqp, $dst->id, $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transfer($this->eqp, $dst->id, $this->admin->id);
    }

    public function test_work_order_lifecycle()
    {
        $svc = app(EquipmentService::class);
        $wo = $svc->createWorkOrder($this->eqp, ['type' => 'corrective', 'scheduled_date' => today()], $this->admin->id);
        $this->assertEquals('under_maintenance', $this->eqp->fresh()->status);
        $svc->closeWorkOrder($wo, 'Fixed', $this->admin->id);
        $this->assertEquals('active', $this->eqp->fresh()->status);
        $this->assertEquals('closed', $wo->fresh()->status);
    }

    public function test_failed_calibration_takes_out_of_service()
    {
        app(EquipmentService::class)->recordCalibration($this->eqp, 'fail', $this->admin->id);
        $this->assertEquals('out_of_service', $this->eqp->fresh()->status);
    }

    public function test_retirement_workflow()
    {
        $svc = app(EquipmentService::class);
        $r = $svc->requestRetirement($this->eqp, 'Beyond repair', $this->admin->id);
        $svc->approveRetirement($r, $this->approver->id);
        $this->assertEquals('retired', $this->eqp->fresh()->status);
    }

    public function test_self_retirement_rejected()
    {
        $svc = app(EquipmentService::class);
        $r = $svc->requestRetirement($this->eqp, 'x', $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->approveRetirement($r, $this->admin->id);
    }
}
