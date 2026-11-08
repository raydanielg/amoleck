<?php
namespace Tests\Feature\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\{Equipment, EquipmentCategory, InventoryBatch, InventoryCategory, InventoryItem, InventoryLedger, PharmacyLocation, Warehouse};
use Modules\Inventory\Services\{EquipmentService, InventoryService, PharmacyService, ProcurementService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class InventoryE2ETest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $approver; private Branch $hq; private Warehouse $wh; private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Inventory')?->enable();
        $this->wh = Warehouse::create(['code' => 'W1', 'name' => 'W', 'branch_id' => $this->hq->id]);
        $cat = InventoryCategory::create(['code' => 'P', 'name' => 'P']);
        $this->item = InventoryItem::create(['code' => 'I', 'name' => 'I', 'inventory_category_id' => $cat->id, 'reorder_level' => 30]);
    }

    /** Full lifecycle: replenish→req→PO→receive→dispense→transfer→equipment—one ledger. */
    public function test_full_inventory_lifecycle()
    {
        $inv = app(InventoryService::class); $proc = app(ProcurementService::class); $ph = app(PharmacyService::class); $eq = app(EquipmentService::class);

        // replenish suggests
        $s = $proc->replenish($this->wh->id);
        $this->assertEquals(1, $s->count()); // 0 < 30 reorder_level

        // procurement: supplier → req → PO → approve → receive
        $sup = $proc->verifySupplier($proc->createSupplier(['name' => 'S'], $this->admin->id), $this->admin->id);
        $r = $proc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->hq->id, 'qty' => 100], $this->admin->id);
        $r->update(['status' => 'approved']);
        $po = $proc->createPo($r->fresh(), $sup->id, 5.00, $this->admin->id);
        $proc->approvePo($po, $this->approver->id);
        $proc->receivePo($po->fresh(), 100, ['batch_number' => 'B1', 'expiry_date' => '2028-01-01'], $this->admin->id);
        $b = InventoryBatch::first();
        $this->assertEquals(100, (float) $b->qty);
        $this->assertEquals('fully_received', $po->fresh()->status);

        // pharmacy: dispense 40 via FEFO
        $loc = PharmacyLocation::create(['code' => 'PH', 'name' => 'Ph', 'branch_id' => $this->hq->id, 'warehouse_id' => $this->wh->id]);
        $d = $ph->open($loc, [], $this->admin->id);
        $ph->review($d, 'approved', $this->admin->id);
        $ph->allocate($d->fresh(), $this->item->id, 40, $this->admin->id);
        $ph->dispense($d->fresh(), $this->admin->id);
        $this->assertEquals(60, (float) $b->fresh()->qty);
        $this->assertEquals('dispensed', $d->fresh()->status);

        // transfer 20 to second warehouse
        $w2 = Warehouse::create(['code' => 'W2', 'name' => 'W2', 'branch_id' => $this->hq->id]);
        $t = $inv->transfer($b->fresh(), $w2->id, 20, $this->admin->id);
        $inv->receiveTransfer($t, $this->admin->id);
        $this->assertEquals(40, (float) $b->fresh()->qty);
        $this->assertEquals(20, (float) InventoryBatch::where('warehouse_id', $w2->id)->first()->qty);

        // ledger reconciles: +100 -40 -20 +20
        $this->assertEquals(4, InventoryLedger::count());
        $this->assertEquals(100, (float) InventoryLedger::where('type', 'receipt')->first()->qty);
        $this->assertEquals(-40, (float) InventoryLedger::where('type', 'issue')->first()->qty);

        // equipment: register → maintain → back
        $cat = EquipmentCategory::create(['code' => 'T', 'name' => 'T']);
        $e = $eq->register(['name' => 'TENS', 'equipment_category_id' => $cat->id, 'serial_number' => 'SN', 'branch_id' => $this->hq->id], $this->admin->id);
        $wo = $eq->createWorkOrder($e, ['type' => 'preventive'], $this->admin->id);
        $eq->closeWorkOrder($wo, 'Done', $this->admin->id);
        $this->assertEquals('active', $e->fresh()->status);
    }
}
