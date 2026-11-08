<?php
namespace Modules\HospitalOperations\Services;
use Illuminate\Support\Facades\DB;
use Modules\HospitalOperations\Models\{EmergencyDischarge, EmergencyDisposition, EmergencyTransfer, EmergencyVisit};
use Modules\Foundation\Support\Audit;

class EmergencyDispositionService
{
    private function num(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "er.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'er', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    public function propose(EmergencyVisit $v, string $type, string $summary, int $userId): EmergencyDisposition
    {
        abort_unless(in_array($v->status, ['triaged', 'in_consultation']), 422);
        $d = EmergencyDisposition::updateOrCreate(['emergency_visit_id' => $v->id, 'status' => 'proposed'], ['type' => $type, 'clinical_summary' => $summary, 'proposed_by' => $userId]);
        Audit::record('er.disposition_proposed', null, null, ['er' => $v->visit_number, 'type' => $type]);
        return $d;
    }

    public function approve(EmergencyDisposition $d, int $userId): EmergencyDisposition
    {
        abort_unless($d->status === 'proposed', 422);
        abort_if($d->proposed_by === $userId, 422, 'Cannot approve own disposition.');
        $d->update(['status' => 'approved', 'approved_by' => $userId]);
        Audit::record('er.disposition_approved', null, null, ['disp' => $d->id]);
        return $d->fresh();
    }

    public function discharge(EmergencyVisit $v, array $data, int $userId): EmergencyDischarge
    {
        $d = EmergencyDischarge::create($data + ['discharge_number' => $this->num('DIS', 'emergency_discharges', 'discharge_number'), 'emergency_visit_id' => $v->id, 'status' => 'pending', 'discharged_by' => $userId]);
        Audit::record('er.discharge_created', null, null, ['dis' => $d->discharge_number]);
        return $d;
    }

    public function authorizeDischarge(EmergencyDischarge $d, int $userId): EmergencyDischarge
    {
        abort_unless($d->status === 'pending', 422);
        abort_if($d->discharged_by === $userId, 422, 'Cannot authorize own discharge.');
        $d->update(['status' => 'authorized', 'authorized_by' => $userId]);
        $d->visit->update(['status' => 'discharged']);
        Audit::record('er.discharge_authorized', null, null, ['dis' => $d->discharge_number]);
        return $d->fresh();
    }

    public function transfer(EmergencyVisit $v, array $data, int $userId): EmergencyTransfer
    {
        $t = EmergencyTransfer::create($data + ['transfer_number' => $this->num('TRN', 'emergency_transfers', 'transfer_number'), 'emergency_visit_id' => $v->id, 'status' => 'pending', 'requested_by' => $userId]);
        Audit::record('er.transfer_requested', null, null, ['trn' => $t->transfer_number]);
        return $t;
    }

    public function receiveTransfer(EmergencyTransfer $t, int $userId): EmergencyTransfer
    {
        abort_unless($t->status === 'pending', 422);
        $t->update(['status' => 'received', 'received_by' => $userId, 'received_at' => now()]);
        Audit::record('er.transfer_received', null, null, ['trn' => $t->transfer_number]);
        return $t->fresh();
    }
}
