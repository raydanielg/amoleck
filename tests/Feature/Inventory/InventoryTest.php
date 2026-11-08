<?php
namespace Tests\Feature\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\{InventoryBatch, InventoryCategory, InventoryItem, InventoryLedger, Warehouse};
use Modules\Inventory\Services\InventoryService;
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private Branch $hq; private Warehouse $wh; private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Inventory')?->enable();
        $this->wh = Warehouse::create(['code' => 'MAIN', 'name' => 'Main', 'branch_id' => $this->hq->id]);
        $cat = InventoryCategory::create(['code' => 'PHAR', 'name' => 'Pharmaceuticals', 'type' => 'pharma']);
        $this->item = InventoryItem::create(['code' => 'PARA', 'name' => 'Paracetamol 500mg', 'inventory_category_id' => $cat->id, 'uom' => 'tablet', 'tracking' => 'batch']);
    }

    private function receive(float $qty = 100, ?string $expiry = null): InventoryBatch
    {
        app(InventoryService::class)->receive($this->wh->id, $this->item->id, $qty, ['batch_number' => 'B1', 'expiry_date' => $expiry], $this->admin->id);
        return InventoryBatch::first();
    }

    public function test_receive_creates_batch_and_ledger() { $b = $this->receive(100); $this->assertEquals(100, (float) $b->qty); $this->assertEquals('receipt', InventoryLedger::first()->type); }
    public function test_issue_deducts_and_ledgers() { $b = $this->receive(100); app(InventoryService::class)->issue($b, 30, $this->admin->id); $this->assertEquals(70, (float) $b->fresh()->qty); $this->assertEquals(-30, (float) InventoryLedger::where('type', 'issue')->first()->qty); }
    public function test_issue_blocks_expired() { $b = $this->receive(50, '2020-01-01'); $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(InventoryService::class)->issue($b, 10, $this->admin->id); }
    public function test_issue_blocks_insufficient() { $b = $this->receive(10); $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(InventoryService::class)->issue($b, 50, $this->admin->id); }
    public function test_transfer_moves_stock() { $b = $this->receive(50); $dst = Warehouse::create(['code' => 'PH', 'name' => 'Pharmacy', 'branch_id' => $this->hq->id]); $svc = app(InventoryService::class); $t = $svc->transfer($b, $dst->id, 20, $this->admin->id); $this->assertEquals(30, (float) $b->fresh()->qty); $svc->receiveTransfer($t, $this->admin->id); $this->assertEquals(20, (float) InventoryBatch::where('warehouse_id', $dst->id)->first()->qty); }
    public function test_double_receive_rejected() { $b = $this->receive(50); $dst = Warehouse::create(['code' => 'PH', 'name' => 'Pharmacy', 'branch_id' => $this->hq->id]); $t = app(InventoryService::class)->transfer($b, $dst->id, 20, $this->admin->id); app(InventoryService::class)->receiveTransfer($t, $this->admin->id); $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(InventoryService::class)->receiveTransfer($t->fresh(), $this->admin->id); }
    public function test_adjustment_records_difference() { $b = $this->receive(100); $adj = app(InventoryService::class)->adjust($b, 95, 'Physical count', $this->admin->id); $this->assertEquals(-5, (float) $adj->difference); $this->assertEquals(95, (float) $b->fresh()->qty); }
    public function test_reservation_reduces_available() { $b = $this->receive(100); $svc = app(InventoryService::class); $svc->reserve($b, 40, $this->admin->id); $this->assertEquals(60, $svc->available($b->fresh())); $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); $svc->reserve($b->fresh(), 70, $this->admin->id); }
    public function test_depleted_status() { $b = $this->receive(10); app(InventoryService::class)->issue($b, 10, $this->admin->id); $this->assertEquals('depleted', $b->fresh()->status); }
}
