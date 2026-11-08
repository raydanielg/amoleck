<?php
namespace Modules\Inventory\Services;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\{Dispensing, InventoryBatch, MedicationReturn, PharmacyLocation};
use Modules\Foundation\Support\Audit;

class PharmacyService
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

    /** Open a dispensing against a location (+ optional order/patient ref). */
    public function open(PharmacyLocation $loc, array $data, int $userId): Dispensing
    {
        $d = Dispensing::create($data + ['dispense_number' => $this->num('DSP', 'dispensings', 'dispense_number'), 'pharmacy_location_id' => $loc->id, 'status' => 'pending_review']);
        Audit::record('pharm.dispense_opened', null, null, ['dsp' => $d->dispense_number], $loc->branch_id);
        return $d;
    }

    /** Pharmacist review — only approved orders can dispense. */
    public function review(Dispensing $d, string $decision, int $userId, ?string $notes = null): Dispensing
    {
        abort_unless($d->status === 'pending_review', 422);
        abort_unless(in_array($decision, ['approved', 'rejected', 'clarification']), 422);
        $d->update(['status' => $decision, 'pharmacist_id' => $userId, 'notes' => $notes]);
        Audit::record("pharm.review_{$decision}", null, null, ['dsp' => $d->dispense_number]);
        return $d->fresh();
    }

    /** FEFO — pick earliest-expiry eligible batches for the ordered qty. */
    public function allocate(Dispensing $d, int $itemId, float $orderedQty, int $userId): float
    {
        abort_unless($d->status === 'approved', 422);
        $batches = InventoryBatch::where('inventory_item_id', $itemId)
            ->where('warehouse_id', $d->location->warehouse_id)
            ->where('status', 'available')
            ->where('qty', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>', today()))
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->get();
        $need = $orderedQty; $allocated = 0;
        foreach ($batches as $b) {
            if ($need <= 0) { break; }
            $take = min((float) $b->qty - (float) $b->reserved, $need);
            if ($take <= 0) { continue; }
            $d->lines()->create(['inventory_item_id' => $itemId, 'inventory_batch_id' => $b->id, 'ordered_qty' => $orderedQty, 'dispensed_qty' => $take, 'remaining_qty' => 0]);
            $need -= $take; $allocated += $take;
        }
        return $allocated; // may be < ordered = partial
    }

    /** Execute dispensing — deducts stock via InventoryService::issue (ledger + idempotent). */
    public function dispense(Dispensing $d, int $userId): Dispensing
    {
        abort_unless($d->status === 'approved', 422);
        return DB::transaction(function () use ($d, $userId) {
            foreach ($d->lines as $line) {
                app(InventoryService::class)->issue($line->batch, (float) $line->dispensed_qty, $userId);
            }
            $d->update(['status' => $d->lines()->whereColumn('dispensed_qty', '<', 'ordered_qty')->exists() ? 'partially_dispensed' : 'dispensed']);
            Audit::record('pharm.dispensed', null, null, ['dsp' => $d->dispense_number]);
            return $d->fresh();
        });
    }

    /** Return → quarantine until inspected. */
    public function return(Dispensing $d, InventoryBatch $b, float $qty, string $reason, int $userId): MedicationReturn
    {
        $r = MedicationReturn::create(['return_number' => $this->num('RET', 'medication_returns', 'return_number'), 'dispensing_id' => $d->id, 'inventory_batch_id' => $b->id, 'qty' => $qty, 'reason' => $reason, 'disposition' => 'quarantine', 'created_by' => $userId]);
        Audit::record('pharm.return', null, null, ['ret' => $r->return_number]);
        return $r;
    }

    /** Inspect a return → restock only after explicit approval. */
    public function inspectReturn(MedicationReturn $r, string $disposition, int $userId): MedicationReturn
    {
        abort_unless($r->disposition === 'quarantine', 422);
        abort_unless(in_array($disposition, ['restocked', 'disposed', 'rejected']), 422);
        $r->update(['disposition' => $disposition, 'inspected_by' => $userId]);
        if ($disposition === 'restocked') { $r->batch->increment('qty', $r->qty); }
        Audit::record("pharm.return_{$disposition}", null, null, ['ret' => $r->return_number]);
        return $r->fresh();
    }
}
