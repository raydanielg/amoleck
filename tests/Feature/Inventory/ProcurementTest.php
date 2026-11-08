<?php
namespace Tests\Feature\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\{InventoryBatch, InventoryCategory, InventoryItem, PurchaseOrder, StockRequisition, Supplier, Warehouse};
use Modules\Inventory\Services\{InventoryService, ProcurementService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $approver; private Warehouse $wh; private InventoryItem $item; private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->approver = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Inventory')?->enable();
        $this->wh = Warehouse::create(['code' => 'W1', 'name' => 'W', 'branch_id' => $hq->id]);
        $cat = InventoryCategory::create(['code' => 'C1', 'name' => 'C']);
        $this->item = InventoryItem::create(['code' => 'I1', 'name' => 'I', 'inventory_category_id' => $cat->id, 'reorder_level' => 50, 'reorder_qty' => 100]);
        $this->supplier = app(ProcurementService::class)->createSupplier(['name' => 'Pharma Co'], $this->admin->id);
    }

    public function test_supplier_verify() { $s = app(ProcurementService::class)->verifySupplier($this->supplier, $this->admin->id); $this->assertEquals('active', $s->status); }

    public function test_requisition_workflow()
    {
        $svc = app(ProcurementService::class);
        $r = $svc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->wh->branch_id, 'qty' => 50], $this->admin->id);
        $svc->transitionRequisition($r, 'submitted', $this->admin->id);
        $svc->transitionRequisition($r->fresh(), 'approved', $this->approver->id);
        $this->assertEquals('approved', $r->fresh()->status);
    }

    public function test_self_approval_rejected()
    {
        $svc = app(ProcurementService::class);
        $r = $svc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->wh->branch_id, 'qty' => 50], $this->admin->id);
        $r->update(['status' => 'submitted']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transitionRequisition($r->fresh(), 'approved', $this->admin->id);
    }

    public function test_po_from_approved_requisition()
    {
        $svc = app(ProcurementService::class);
        $svc->verifySupplier($this->supplier, $this->admin->id);
        $r = $svc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->wh->branch_id, 'qty' => 50], $this->admin->id);
        $r->update(['status' => 'approved']);
        $po = $svc->createPo($r->fresh(), $this->supplier->id, 10.00, $this->admin->id);
        $this->assertMatchesRegularExpression('/^PO-\d{4}-\d{6}$/', $po->po_number);
        $this->assertEquals('converted', $r->fresh()->status);
    }

    public function test_po_from_unapproved_rejected()
    {
        $svc = app(ProcurementService::class);
        $svc->verifySupplier($this->supplier, $this->admin->id);
        $r = $svc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->wh->branch_id, 'qty' => 50], $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->createPo($r, $this->supplier->id, 10.00, $this->admin->id); // draft req → 422
    }

    public function test_inactive_supplier_rejected()
    {
        $svc = app(ProcurementService::class);
        $r = $svc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->wh->branch_id, 'qty' => 50], $this->admin->id);
        $r->update(['status' => 'approved']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->createPo($r->fresh(), $this->supplier->id, 10.00, $this->admin->id); // supplier still pending
    }

    public function test_po_receiving_updates_stock_and_status()
    {
        $svc = app(ProcurementService::class);
        $svc->verifySupplier($this->supplier, $this->admin->id);
        $r = $svc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->wh->branch_id, 'qty' => 50], $this->admin->id);
        $r->update(['status' => 'approved']);
        $po = $svc->createPo($r->fresh(), $this->supplier->id, 10.00, $this->admin->id);
        $svc->approvePo($po, $this->approver->id);
        $svc->receivePo($po->fresh(), 20, ['batch_number' => 'B1'], $this->admin->id);
        $this->assertEquals('partially_received', $po->fresh()->status);
        $this->assertEquals(20, (float) InventoryBatch::first()->qty);
        $svc->receivePo($po->fresh(), 30, ['batch_number' => 'B1'], $this->admin->id);
        $this->assertEquals('fully_received', $po->fresh()->status);
        $this->assertEquals(50, (float) InventoryBatch::first()->qty);
    }

    public function test_over_receiving_rejected()
    {
        $svc = app(ProcurementService::class);
        $svc->verifySupplier($this->supplier, $this->admin->id);
        $r = $svc->createRequisition(['inventory_item_id' => $this->item->id, 'warehouse_id' => $this->wh->id, 'branch_id' => $this->wh->branch_id, 'qty' => 50], $this->admin->id);
        $r->update(['status' => 'approved']);
        $po = $svc->createPo($r->fresh(), $this->supplier->id, 10.00, $this->admin->id);
        $svc->approvePo($po, $this->approver->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->receivePo($po->fresh(), 60, [], $this->admin->id); // 60 > 50 ordered
    }

    public function test_replenishment_suggests_below_reorder()
    {
        $s = app(ProcurementService::class)->replenish($this->wh->id);
        $this->assertEquals(1, $s->count()); // 0 available < 50 reorder_level → suggest
        $this->assertEquals(100, (float) $s->first()->suggested_qty); // reorder_qty
    }
}
