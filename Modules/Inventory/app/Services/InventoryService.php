<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Models\InventoryLedger;
use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockReceipt;
use Modules\Inventory\Models\StockReservation;
use Modules\Inventory\Models\StockTransfer;
use Modules\Foundation\Support\Audit;

class InventoryService
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

    private function ledger(InventoryBatch $b, string $type, float $qty, string $srcType, int $srcId, int $userId): void
    {
        $new = (float) $b->qty;
        InventoryLedger::create(['inventory_item_id' => $b->inventory_item_id, 'inventory_batch_id' => $b->id, 'warehouse_id' => $b->warehouse_id, 'type' => $type, 'qty' => $qty, 'balance' => $new, 'source_type' => $srcType, 'source_id' => $srcId, 'created_by' => $userId]);
    }

    /** Receive stock → creates/updates batch + ledger. */
    public function receive(int $warehouseId, int $itemId, float $qty, array $data, int $userId): StockReceipt
    {
        abort_if($qty <= 0, 422);
        return DB::transaction(function () use ($warehouseId, $itemId, $qty, $data, $userId) {
            $r = StockReceipt::create(['receipt_number' => $this->num('RCV', 'stock_receipts', 'receipt_number'), 'warehouse_id' => $warehouseId, 'source' => $data['source'] ?? 'po', 'status' => 'received', 'received_by' => $userId]);
            $b = InventoryBatch::firstOrNew(['inventory_item_id' => $itemId, 'warehouse_id' => $warehouseId, 'batch_number' => $data['batch_number'] ?? null]);
            $b->expiry_date = $data['expiry_date'] ?? $b->expiry_date;
            $b->unit_cost = $data['unit_cost'] ?? $b->unit_cost;
            $b->qty = bcadd((string) $b->qty, (string) $qty, 3);
            $b->status = 'available';
            $b->save();
            $this->ledger($b->fresh(), 'receipt', $qty, 'stock_receipt', $r->id, $userId);
            Audit::record('inv.received', null, null, ['rcv' => $r->receipt_number, 'qty' => $qty]);
            return $r;
        });
    }

    /** Issue stock — blocks expired/quarantined/insufficient. */
    public function issue(InventoryBatch $b, float $qty, int $userId): StockIssue
    {
        abort_if($b->status !== 'available', 422, "Batch {$b->status}.");
        abort_if($b->expiry_date && $b->expiry_date->isPast(), 422, 'Expired batch.');
        return DB::transaction(function () use ($b, $qty, $userId) {
            $b->lockForUpdate()->find($b->id);
            abort_if(bccomp(bcsub($b->qty, $b->reserved, 3), (string) $qty, 3) < 0, 422, 'Insufficient stock.');
            $i = StockIssue::create(['issue_number' => $this->num('ISS', 'stock_issues', 'issue_number'), 'warehouse_id' => $b->warehouse_id, 'inventory_batch_id' => $b->id, 'qty' => $qty, 'status' => 'issued', 'issued_by' => $userId]);
            $b->decrement('qty', $qty);
            if (bccomp((string) $b->fresh()->qty, '0', 3) <= 0) { $b->update(['status' => 'depleted']); }
            $this->ledger($b->fresh(), 'issue', -$qty, 'stock_issue', $i->id, $userId);
            Audit::record('inv.issued', null, null, ['iss' => $i->issue_number]);
            return $i;
        });
    }

    /** Transfer: dispatch deducts source → receive adds destination. */
    public function transfer(InventoryBatch $src, int $toWarehouseId, float $qty, int $userId): StockTransfer
    {
        abort_if(bccomp((string) $src->qty, (string) $qty, 3) < 0, 422, 'Insufficient.');
        return DB::transaction(function () use ($src, $toWarehouseId, $qty, $userId) {
            $t = StockTransfer::create(['transfer_number' => $this->num('TRF', 'stock_transfers', 'transfer_number'), 'inventory_batch_id' => $src->id, 'from_warehouse_id' => $src->warehouse_id, 'to_warehouse_id' => $toWarehouseId, 'qty' => $qty, 'status' => 'in_transit', 'created_by' => $userId]);
            $src->decrement('qty', $qty);
            $this->ledger($src->fresh(), 'transfer_out', -$qty, 'stock_transfer', $t->id, $userId);
            Audit::record('inv.transfer_dispatched', null, null, ['trf' => $t->transfer_number]);
            return $t;
        });
    }

    public function receiveTransfer(StockTransfer $t, int $userId): StockTransfer
    {
        abort_unless($t->status === 'in_transit', 422);
        return DB::transaction(function () use ($t, $userId) {
            $src = $t->batch;
            $dst = InventoryBatch::firstOrNew(['inventory_item_id' => $src->inventory_item_id, 'warehouse_id' => $t->to_warehouse_id, 'batch_number' => $src->batch_number]);
            $dst->expiry_date = $src->expiry_date;
            $dst->qty = bcadd((string) $dst->qty, (string) $t->qty, 3);
            $dst->status = 'available';
            $dst->save();
            $t->update(['status' => 'received', 'received_by' => $userId]);
            $this->ledger($dst->fresh(), 'transfer_in', (float) $t->qty, 'stock_transfer', $t->id, $userId);
            Audit::record('inv.transfer_received', null, null, ['trf' => $t->transfer_number]);
            return $t->fresh();
        });
    }

    /** Controlled adjustment — no silent stock change. */
    public function adjust(InventoryBatch $b, float $counted, string $reason, int $userId): StockAdjustment
    {
        return DB::transaction(function () use ($b, $counted, $reason, $userId) {
            $diff = bcsub((string) $counted, (string) $b->qty, 3);
            $adj = StockAdjustment::create(['inventory_batch_id' => $b->id, 'system_qty' => $b->qty, 'counted_qty' => $counted, 'difference' => $diff, 'reason' => $reason, 'status' => 'approved', 'approved_by' => $userId, 'created_by' => $userId]);
            $b->update(['qty' => $counted]);
            $this->ledger($b->fresh(), 'adjustment', (float) $diff, 'stock_adjustment', $adj->id, $userId);
            Audit::record('inv.adjusted', null, null, ['diff' => $diff]);
            return $adj;
        });
    }

    /** Reserve stock — available = qty − reserved. */
    public function reserve(InventoryBatch $b, float $qty, int $userId): StockReservation
    {
        return DB::transaction(function () use ($b, $qty, $userId) {
            abort_if(bccomp(bcsub($b->qty, $b->reserved, 3), (string) $qty, 3) < 0, 422, 'Insufficient available.');
            $r = StockReservation::create(['inventory_batch_id' => $b->id, 'qty' => $qty, 'status' => 'reserved', 'created_by' => $userId]);
            $b->increment('reserved', $qty);
            $this->ledger($b->fresh(), 'reservation', $qty, 'stock_reservation', $r->id, $userId);
            return $r;
        });
    }

    public function available(InventoryBatch $b): float
    {
        return (float) bcsub($b->qty, $b->reserved, 3);
    }
}
