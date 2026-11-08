<?php
namespace Tests\Feature\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\{Dispensing, InventoryBatch, InventoryCategory, InventoryItem, MedicationReturn, PharmacyLocation, Warehouse};
use Modules\Inventory\Services\{InventoryService, PharmacyService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private PharmacyLocation $loc; private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Inventory')?->enable();
        $wh = Warehouse::create(['code' => 'PH', 'name' => 'Pharmacy', 'branch_id' => $hq->id]);
        $this->loc = PharmacyLocation::create(['code' => 'PH1', 'name' => 'Main Pharmacy', 'branch_id' => $hq->id, 'warehouse_id' => $wh->id]);
        $cat = InventoryCategory::create(['code' => 'PHAR', 'name' => 'P', 'type' => 'pharma']);
        $this->item = InventoryItem::create(['code' => 'PARA', 'name' => 'Para', 'inventory_category_id' => $cat->id]);
    }

    private function stock(float $qty, ?string $exp = null, string $bn = 'B1'): void
    {
        app(InventoryService::class)->receive($this->loc->warehouse_id, $this->item->id, $qty, ['batch_number' => $bn, 'expiry_date' => $exp], $this->admin->id);
    }

    private function open(): Dispensing
    {
        return app(PharmacyService::class)->open($this->loc, ['patient_id' => 1], $this->admin->id);
    }

    public function test_dispense_workflow()
    {
        $this->stock(100);
        $svc = app(PharmacyService::class);
        $d = $this->open();
        $svc->review($d, 'approved', $this->admin->id);
        $svc->allocate($d->fresh(), $this->item->id, 30, $this->admin->id);
        $svc->dispense($d->fresh(), $this->admin->id);
        $this->assertEquals('dispensed', $d->fresh()->status);
        $this->assertEquals(70, (float) InventoryBatch::first()->qty);
    }

    public function test_unapproved_cannot_dispense()
    {
        $this->stock(100);
        $d = $this->open();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PharmacyService::class)->allocate($d, $this->item->id, 10, $this->admin->id);
    }

    public function test_partial_dispensing()
    {
        $this->stock(20);
        $svc = app(PharmacyService::class);
        $d = $this->open();
        $svc->review($d, 'approved', $this->admin->id);
        $allocated = $svc->allocate($d->fresh(), $this->item->id, 50, $this->admin->id);
        $this->assertEquals(20, $allocated); // only 20 available
    }

    public function test_fefo_selects_earliest_expiry()
    {
        $this->stock(50, '2028-06-01', 'LATE');
        $this->stock(50, '2027-06-01', 'EARLY');
        $svc = app(PharmacyService::class);
        $d = $this->open();
        $svc->review($d, 'approved', $this->admin->id);
        $svc->allocate($d->fresh(), $this->item->id, 30, $this->admin->id);
        $line = $d->fresh()->lines()->first();
        $this->assertEquals('EARLY', InventoryBatch::find($line->inventory_batch_id)->batch_number);
    }

    public function test_expired_batch_excluded()
    {
        $this->stock(50, '2020-01-01', 'EXPIRED');
        $this->stock(50, '2028-01-01', 'GOOD');
        $svc = app(PharmacyService::class);
        $d = $this->open();
        $svc->review($d, 'approved', $this->admin->id);
        $svc->allocate($d->fresh(), $this->item->id, 30, $this->admin->id);
        $line = $d->fresh()->lines()->first();
        $this->assertEquals('GOOD', InventoryBatch::find($line->inventory_batch_id)->batch_number);
    }

    public function test_return_goes_to_quarantine()
    {
        $this->stock(100);
        $svc = app(PharmacyService::class);
        $d = $this->open();
        $svc->review($d, 'approved', $this->admin->id);
        $svc->allocate($d->fresh(), $this->item->id, 30, $this->admin->id);
        $svc->dispense($d->fresh(), $this->admin->id);
        $r = $svc->return($d->fresh(), InventoryBatch::first(), 10, 'Patient declined', $this->admin->id);
        $this->assertEquals('quarantine', $r->disposition);
        $this->assertEquals(70, (float) InventoryBatch::first()->qty); // not restocked yet
    }

    public function test_approved_restock()
    {
        $this->stock(100);
        $svc = app(PharmacyService::class);
        $d = $this->open();
        $svc->review($d, 'approved', $this->admin->id);
        $svc->allocate($d->fresh(), $this->item->id, 30, $this->admin->id);
        $svc->dispense($d->fresh(), $this->admin->id);
        $r = $svc->return($d->fresh(), InventoryBatch::first(), 10, 'x', $this->admin->id);
        $svc->inspectReturn($r, 'restocked', $this->admin->id);
        $this->assertEquals(80, (float) InventoryBatch::first()->qty); // 70 + 10 back
    }

    public function test_stock_deducted_once_via_ledger()
    {
        $this->stock(100);
        $svc = app(PharmacyService::class);
        $d = $this->open();
        $svc->review($d, 'approved', $this->admin->id);
        $svc->allocate($d->fresh(), $this->item->id, 30, $this->admin->id);
        $svc->dispense($d->fresh(), $this->admin->id);
        $this->assertEquals(70, (float) InventoryBatch::first()->qty);
    }
}
