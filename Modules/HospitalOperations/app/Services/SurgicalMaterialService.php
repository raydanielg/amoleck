<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\SurgicalCount;
use Modules\HospitalOperations\Models\SurgicalConsumableUsage;
use Modules\HospitalOperations\Models\SurgicalImplant;
use Modules\HospitalOperations\Models\SurgicalIncident;
use Modules\HospitalOperations\Models\SurgicalOperation;
use Modules\HospitalOperations\Models\SurgicalSpecimen;
use Modules\Inventory\Models\InventoryBatch;
use Modules\Inventory\Services\InventoryService;

class SurgicalMaterialService
{
    public const COUNT_TYPES = ['instrument', 'swab', 'needle', 'other'];
    public const COUNT_PHASES = ['initial', 'additional', 'final'];

    private function nextNumber(string $key, string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($key, $prefix, $table, $col) {
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y').'-%')->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'surgery', 'value' => json_encode($seq), 'is_secret' => false, 'created_at' => now(), 'updated_at' => now()]);
            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    // ---- Counts ----
    public function recordCount(SurgicalOperation $op, array $data, int $userId): SurgicalCount
    {
        abort_unless($op->status === 'in_progress', 422, 'Operation not in progress.');
        abort_unless(in_array($data['type'], self::COUNT_TYPES) && in_array($data['phase'], self::COUNT_PHASES), 422, 'Invalid count.');
        abort_if(SurgicalCount::where('surgical_operation_id', $op->id)->where('type', $data['type'])->where('phase', $data['phase'])->exists(), 422, 'Count already recorded.');

        $items = collect($data['items'] ?? [])->map(fn ($i) => [
            'item_name' => $i['item_name'], 'expected_qty' => (int) ($i['expected_qty'] ?? 0),
            'counted_qty' => (int) ($i['counted_qty'] ?? 0),
            'difference' => (int) ($i['counted_qty'] ?? 0) - (int) ($i['expected_qty'] ?? 0),
        ]);
        $discrepancy = $items->contains(fn ($i) => $i['difference'] !== 0);

        return DB::transaction(function () use ($op, $data, $items, $discrepancy, $userId) {
            $count = $op->counts()->create([
                'type' => $data['type'], 'phase' => $data['phase'],
                'status' => $discrepancy ? 'discrepancy' : 'matched',
                'discrepancy_notes' => $discrepancy ? ($data['discrepancy_notes'] ?? 'Quantity mismatch') : null,
                'counted_by' => $userId, 'verified_by' => $data['verified_by'] ?? null,
            ]);
            $count->items()->createMany($items->all());
            if ($discrepancy) {
                SurgicalIncident::create([
                    'surgical_case_id' => $op->surgical_case_id, 'surgical_operation_id' => $op->id,
                    'patient_id' => $op->patient_id, 'branch_id' => $op->branch_id,
                    'category' => 'count_discrepancy', 'event_at' => now(),
                    'description' => "Count discrepancy ({$count->type}/{$count->phase}): ".($data['discrepancy_notes'] ?? 'quantity mismatch'),
                    'severity' => 'high', 'status' => 'open', 'reported_by' => $userId,
                ]);
            }
            Audit::record('surgery.count_recorded', $op->patient, null, ['type' => $count->type, 'phase' => $count->phase, 'status' => $count->status], $op->branch_id);
            return $count->fresh('items');
        });
    }

    /** Resolve a discrepancy — never silent; always documented. */
    public function resolveCount(SurgicalCount $count, string $resolution, int $userId): SurgicalCount
    {
        abort_unless($count->status === 'discrepancy', 422, 'No open discrepancy.');
        abort_if(! trim($resolution), 422, 'Resolution required.');
        $count->update(['status' => 'resolved', 'resolution' => $resolution, 'resolved_by' => $userId, 'resolved_at' => now()]);
        Audit::record('surgery.count_resolved', $count->operation->patient, null, ['count' => $count->id], $count->operation->branch_id);
        return $count->fresh();
    }

    // ---- Specimens ----
    public function registerSpecimen(SurgicalOperation $op, array $data, int $userId): SurgicalSpecimen
    {
        abort_unless($op->status === 'in_progress', 422, 'Operation not in progress.');
        $s = $op->specimens()->create($data + [
            'specimen_number' => $this->nextNumber('surgery.specimen_sequence', 'SPC', 'surgical_specimens', 'specimen_number'),
            'surgical_case_id' => $op->surgical_case_id, 'patient_id' => $op->patient_id,
            'collected_at' => $data['collected_at'] ?? now(), 'collected_by' => $userId, 'status' => 'collected',
        ]);
        Audit::record('surgery.specimen_registered', $op->patient, null, ['spc' => $s->specimen_number], $op->branch_id);
        return $s;
    }

    public function dispatchSpecimen(SurgicalSpecimen $s, array $data, int $userId): SurgicalSpecimen
    {
        abort_unless($s->status === 'collected', 422);
        $s->handovers()->create([
            'from_user_id' => $userId, 'received_by_name' => $data['received_by_name'] ?? null,
            'dispatched_at' => now(), 'notes' => $data['notes'] ?? null,
        ]);
        $s->update(['status' => 'dispatched', 'laboratory_order_id' => $data['laboratory_order_id'] ?? $s->laboratory_order_id]);
        Audit::record('surgery.specimen_dispatched', $s->operation->patient, null, ['spc' => $s->specimen_number], $s->operation->branch_id);
        return $s->fresh();
    }

    public function receiveSpecimen(SurgicalSpecimen $s, int $userId, ?int $toUserId = null): SurgicalSpecimen
    {
        abort_unless($s->status === 'dispatched', 422);
        $s->handovers()->latest('id')->first()?->update(['to_user_id' => $toUserId ?? $userId, 'received_at' => now()]);
        $s->update(['status' => 'received']);
        Audit::record('surgery.specimen_received', $s->operation->patient, null, ['spc' => $s->specimen_number], $s->operation->branch_id);
        return $s->fresh();
    }

    // ---- Implants ----
    public function recordImplant(SurgicalOperation $op, array $data, int $userId): SurgicalImplant
    {
        abort_unless($op->status === 'in_progress', 422);
        abort_if(! empty($data['expiry_date']) && $data['expiry_date'] < now()->toDateString(), 422, 'Expired implant cannot be recorded.');
        $i = $op->implants()->create($data + [
            'surgical_case_id' => $op->surgical_case_id, 'patient_id' => $op->patient_id, 'recorded_by' => $userId,
        ]);
        Audit::record('surgery.implant_recorded', $op->patient, null, ['implant' => $i->item_name, 'serial' => $i->serial_number], $op->branch_id);
        return $i;
    }

    // ---- Consumables via canonical stock ledger ----
    /** Issue consumables exactly once per (operation,item,batch) — enforced by unique constraint. */
    public function recordConsumable(SurgicalOperation $op, array $data, int $userId): SurgicalConsumableUsage
    {
        abort_unless($op->status === 'in_progress', 422);
        $batch = InventoryBatch::findOrFail($data['inventory_batch_id']);
        abort_unless((int) $batch->inventory_item_id === (int) $data['inventory_item_id'], 422, 'Batch does not belong to item.');
        abort_if($batch->expiry_date && $batch->expiry_date->isPast(), 422, 'Expired batch.');
        abort_if($batch->status !== 'available', 422, "Batch {$batch->status}.");

        return DB::transaction(function () use ($op, $data, $batch, $userId) {
            $existing = SurgicalConsumableUsage::where('surgical_operation_id', $op->id)
                ->where('inventory_item_id', $data['inventory_item_id'])
                ->where('inventory_batch_id', $batch->id)->first();
            abort_if($existing, 422, 'Consumable already recorded for this operation.');
            $issue = app(InventoryService::class)->issue($batch, (float) $data['qty_used'], $userId);
            $u = $op->consumables()->create([
                'surgical_case_id' => $op->surgical_case_id, 'patient_id' => $op->patient_id,
                'inventory_item_id' => $data['inventory_item_id'], 'inventory_batch_id' => $batch->id,
                'warehouse_id' => $batch->warehouse_id, 'qty_used' => $data['qty_used'],
                'qty_returned' => $data['qty_returned'] ?? 0, 'qty_wasted' => $data['qty_wasted'] ?? 0,
                'stock_issue_id' => $issue->id, 'recorded_by' => $userId,
            ]);
            Audit::record('surgery.consumable_used', $op->patient, null, ['item' => $u->inventory_item_id, 'qty' => $u->qty_used, 'issue' => $issue->issue_number], $op->branch_id);
            return $u;
        });
    }
}
