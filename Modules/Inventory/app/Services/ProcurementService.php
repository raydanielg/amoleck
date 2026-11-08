<?php
namespace Modules\Inventory\Services;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\{InventoryBatch, PurchaseOrder, StockRequisition, ReplenishmentSuggestion, Supplier};
use Modules\Foundation\Support\Audit;

class ProcurementService
{
    private function num(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "inv.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'inv', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    public function createSupplier(array $data, int $userId): Supplier
    {
        $s = Supplier::create($data + ['code' => $this->num('SUP', 'suppliers', 'code'), 'status' => 'pending']);
        Audit::record('inv.supplier_created', null, null, ['sup' => $s->code]);
        return $s;
    }

    public function verifySupplier(Supplier $s, int $userId): Supplier
    {
        abort_unless($s->status === 'pending', 422);
        $s->update(['status' => 'active']);
        Audit::record('inv.supplier_verified', null, null, ['sup' => $s->code]);
        return $s->fresh();
    }

    /** Replenishment: available + incoming < reorder_level → suggest reorder to max. */
    public function replenish(int $warehouseId): \Illuminate\Support\Collection
    {
        $suggestions = collect();
        foreach (\Modules\Inventory\Models\InventoryItem::whereNotNull('reorder_level')->get() as $item) {
            $itemId = $item->id;
            $group = InventoryBatch::where('warehouse_id', $warehouseId)->where('inventory_item_id', $itemId)->get();
            $available = $group->sum(fn ($b) => (float) $b->qty - (float) $b->reserved);
            $incoming = PurchaseOrder::where('inventory_item_id', $itemId)->where('warehouse_id', $warehouseId)->whereIn('status', ['approved', 'issued'])->sum(DB::raw('qty - received_qty'));
            if ($available + $incoming < $item->reorder_level) {
                $suggestions->push(ReplenishmentSuggestion::updateOrCreate(
                    ['inventory_item_id' => $itemId, 'warehouse_id' => $warehouseId, 'status' => 'suggested'],
                    ['available' => $available, 'outgoing_reserved' => $group->sum('reserved'), 'suggested_qty' => $item->reorder_qty ?? max(0, $item->reorder_level - $available - $incoming)]
                ));
            }
        }
        return $suggestions;
    }

    public function createRequisition(array $data, int $userId): StockRequisition
    {
        $r = StockRequisition::create($data + ['requisition_number' => $this->num('PR', 'stock_requisitions', 'requisition_number'), 'status' => 'draft', 'requested_by' => $userId]);
        Audit::record('inv.requisition_created', null, null, ['pr' => $r->requisition_number], $r->branch_id);
        return $r;
    }

    public function transitionRequisition(StockRequisition $r, string $to, int $userId): StockRequisition
    {
        $map = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected'], 'approved' => ['converted'], 'rejected' => [], 'converted' => [], 'cancelled' => []];
        abort_unless(in_array($to, $map[$r->status] ?? []), 422, "Cannot {$r->status}→{$to}.");
        abort_if($to === 'approved' && $r->requested_by === $userId, 422, 'Cannot approve own requisition.');
        $r->update(['status' => $to] + ($to === 'approved' ? ['approved_by' => $userId] : []));
        Audit::record("inv.requisition_{$to}", null, null, ['pr' => $r->requisition_number]);
        return $r->fresh();
    }

    public function createPo(StockRequisition $r, int $supplierId, float $unitPrice, int $userId): PurchaseOrder
    {
        abort_unless($r->status === 'approved', 422);
        abort_unless(Supplier::findOrFail($supplierId)->status === 'active', 422, 'Supplier not active.');
        $po = PurchaseOrder::create(['po_number' => $this->num('PO', 'purchase_orders', 'po_number'), 'supplier_id' => $supplierId, 'inventory_item_id' => $r->inventory_item_id, 'warehouse_id' => $r->warehouse_id, 'stock_requisition_id' => $r->id, 'qty' => $r->qty, 'unit_price' => $unitPrice, 'status' => 'draft', 'created_by' => $userId]);
        $r->update(['status' => 'converted']);
        Audit::record('inv.po_created', null, null, ['po' => $po->po_number]);
        return $po;
    }

    public function approvePo(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        abort_unless($po->status === 'draft', 422);
        abort_if($po->created_by === $userId, 422, 'Cannot approve own PO.');
        $po->update(['status' => 'approved', 'approved_by' => $userId]);
        Audit::record('inv.po_approved', null, null, ['po' => $po->po_number]);
        return $po->fresh();
    }

    /** Receive against PO → routes through InventoryService::receive (ledger). */
    public function receivePo(PurchaseOrder $po, float $qty, array $data, int $userId): void
    {
        abort_unless(in_array($po->status, ['approved', 'issued', 'partially_received']), 422);
        abort_if(bccomp(bcadd($po->received_qty, $qty, 3), $po->qty, 3) > 0, 422, 'Exceeds ordered qty.');
        app(InventoryService::class)->receive($po->warehouse_id, $po->inventory_item_id, $qty, $data, $userId);
        $po->increment('received_qty', $qty);
        $po->update(['status' => bccomp((string) $po->fresh()->received_qty, $po->qty, 3) >= 0 ? 'fully_received' : 'partially_received']);
        Audit::record('inv.po_received', null, null, ['po' => $po->po_number, 'qty' => $qty]);
    }
}
