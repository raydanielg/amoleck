<?php
namespace Modules\Inventory\Services;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\{Equipment, EquipmentCalibration, EquipmentDowntime, EquipmentRetirement, EquipmentTransfer, MaintenanceWorkOrder};
use Modules\Foundation\Support\Audit;

class EquipmentService
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

    public function register(array $data, int $userId): Equipment
    {
        $e = Equipment::create($data + ['equipment_number' => $this->num('EQP', 'equipment', 'equipment_number'), 'status' => 'active']);
        Audit::record('inv.equipment_registered', null, null, ['eqp' => $e->equipment_number], $e->branch_id);
        return $e;
    }

    public function transfer(Equipment $e, int $toBranchId, int $userId): EquipmentTransfer
    {
        abort_if(EquipmentTransfer::where('equipment_id', $e->id)->where('status', 'pending')->exists(), 422, 'Transfer already pending.');
        $t = EquipmentTransfer::create(['equipment_id' => $e->id, 'from_branch_id' => $e->branch_id, 'to_branch_id' => $toBranchId, 'status' => 'pending', 'requested_by' => $userId]);
        Audit::record('inv.equipment_transfer_requested', null, null, ['eqp' => $e->equipment_number]);
        return $t;
    }

    public function approveTransfer(EquipmentTransfer $t, int $userId): EquipmentTransfer
    {
        abort_unless($t->status === 'pending', 422);
        abort_if($t->requested_by === $userId, 422, 'Cannot approve own transfer.');
        $t->update(['status' => 'approved', 'approved_by' => $userId]);
        $t->equipment->update(['branch_id' => $t->to_branch_id]);
        Audit::record('inv.equipment_transferred', null, null, ['eqp' => $t->equipment->equipment_number]);
        return $t->fresh();
    }

    public function createWorkOrder(Equipment $e, array $data, int $userId): MaintenanceWorkOrder
    {
        $wo = MaintenanceWorkOrder::create($data + ['wo_number' => $this->num('WO', 'maintenance_work_orders', 'wo_number'), 'equipment_id' => $e->id, 'status' => 'open', 'created_by' => $userId]);
        $e->update(['status' => 'under_maintenance']);
        EquipmentDowntime::create(['equipment_id' => $e->id, 'started_at' => now(), 'reason' => $data['type'] ?? 'maintenance']);
        Audit::record('inv.work_order_created', null, null, ['wo' => $wo->wo_number, 'eqp' => $e->equipment_number]);
        return $wo;
    }

    public function closeWorkOrder(MaintenanceWorkOrder $wo, ?string $findings, int $userId): MaintenanceWorkOrder
    {
        abort_unless($wo->status === 'open', 422);
        $wo->update(['status' => 'closed', 'findings' => $findings]);
        $wo->equipment->update(['status' => 'active']);
        $wo->equipment->downtime()->whereNull('ended_at')->latest()->first()?->update(['ended_at' => now()]);
        Audit::record('inv.work_order_closed', null, null, ['wo' => $wo->wo_number]);
        return $wo->fresh();
    }

    /** Calibration — a fail flags equipment out of service. */
    public function recordCalibration(Equipment $e, string $result, int $userId, ?string $nextDue = null): EquipmentCalibration
    {
        $c = EquipmentCalibration::create(['equipment_id' => $e->id, 'calibrated_at' => today(), 'next_due' => $nextDue, 'result' => $result, 'performed_by' => $userId]);
        if ($result === 'fail') { $e->update(['status' => 'out_of_service']); }
        Audit::record("inv.calibration_{$result}", null, null, ['eqp' => $e->equipment_number]);
        return $c;
    }

    public function requestRetirement(Equipment $e, string $reason, int $userId): EquipmentRetirement
    {
        $r = EquipmentRetirement::create(['equipment_id' => $e->id, 'reason' => $reason, 'status' => 'requested', 'requested_by' => $userId]);
        Audit::record('inv.retirement_requested', null, null, ['eqp' => $e->equipment_number]);
        return $r;
    }

    public function approveRetirement(EquipmentRetirement $r, int $userId): EquipmentRetirement
    {
        abort_unless($r->status === 'requested', 422);
        abort_if($r->requested_by === $userId, 422, 'Cannot approve own retirement.');
        $r->update(['status' => 'approved', 'approved_by' => $userId]);
        $r->equipment->update(['status' => 'retired']);
        Audit::record('inv.retirement_approved', null, null, ['eqp' => $r->equipment->equipment_number]);
        return $r->fresh();
    }

    public function downtimeHours(Equipment $e): float
    {
        return $e->downtime()->whereNotNull('ended_at')->get()->sum(fn ($d) => $d->started_at->diffInMinutes($d->ended_at) / 60);
    }
}
