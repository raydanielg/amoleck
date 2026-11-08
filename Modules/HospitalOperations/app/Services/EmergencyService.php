<?php
namespace Modules\HospitalOperations\Services;
use Illuminate\Support\Facades\DB;
use Modules\HospitalOperations\Models\{EmergencyAcuityCategory, EmergencyAlert, EmergencyHandover, EmergencyTriageAssessment, EmergencyVisit};
use Modules\Foundation\Support\Audit;

class EmergencyService
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

    /** Register arrival — existing patient or TEMP- unidentified. */
    public function arrive(array $data, int $userId): EmergencyVisit
    {
        abort_unless($data['patient_id'] ?? $data['unidentified'] ?? false, 422, 'Patient or unidentified flag required.');
        $v = EmergencyVisit::create($data + [
            'visit_number' => $this->num('ER', 'emergency_visits', 'visit_number'),
            'temp_patient_number' => ($data['unidentified'] ?? false) ? $this->num('TEMP', 'emergency_visits', 'temp_patient_number') : null,
            'status' => 'arrived', 'registered_by' => $userId,
        ]);
        Audit::record('er.arrived', null, null, ['er' => $v->visit_number], $v->branch_id);
        return $v;
    }

    /** Triage — records an assessment (immutable history). */
    public function assess(EmergencyVisit $v, int $acuityId, array $data, int $userId): EmergencyTriageAssessment
    {
        abort_unless($v->status === 'arrived' || $v->status === 'triaged', 422);
        $a = $v->assessments()->create($data + ['emergency_acuity_category_id' => $acuityId, 'assessed_by' => $userId]);
        $v->update(['status' => 'triaged']);
        Audit::record('er.triaged', null, null, ['er' => $v->visit_number, 'acuity' => EmergencyAcuityCategory::find($acuityId)->code], $v->branch_id);
        return $a;
    }

    /** Reassess — new record, never overwrite. */
    public function reassess(EmergencyVisit $v, int $acuityId, array $data, int $userId): EmergencyTriageAssessment
    {
        abort_unless($v->status === 'triaged', 422);
        return $this->assess($v, $acuityId, $data, $userId);
    }

    public function alert(EmergencyVisit $v, string $type, int $userId): EmergencyAlert
    {
        $a = EmergencyAlert::create(['emergency_visit_id' => $v->id, 'type' => $type, 'status' => 'active', 'raised_by' => $userId]);
        Audit::record('er.alert', null, null, ['er' => $v->visit_number, 'type' => $type], $v->branch_id);
        return $a;
    }

    public function acknowledgeAlert(EmergencyAlert $a, int $userId): EmergencyAlert
    {
        abort_unless($a->status === 'active', 422);
        $a->update(['status' => 'acknowledged', 'acknowledged_by' => $userId, 'acknowledged_at' => now()]);
        Audit::record('er.alert_acknowledged', null, null, ['alert' => $a->id]);
        return $a->fresh();
    }

    public function handover(EmergencyVisit $v, int $toUserId, ?string $notes, int $userId): EmergencyHandover
    {
        $h = EmergencyHandover::create(['emergency_visit_id' => $v->id, 'from_user_id' => $userId, 'to_user_id' => $toUserId, 'notes' => $notes, 'status' => 'pending']);
        Audit::record('er.handover', null, null, ['er' => $v->visit_number], $v->branch_id);
        return $h;
    }

    public function acknowledgeHandover(EmergencyHandover $h, int $userId): EmergencyHandover
    {
        abort_unless($h->status === 'pending', 422);
        abort_unless($h->to_user_id === $userId, 422, 'Only the receiver can acknowledge.');
        $h->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        Audit::record('er.handover_acknowledged', null, null, ['hov' => $h->id]);
        return $h->fresh();
    }

    public function dashboard(int $branchId): array
    {
        $q = EmergencyVisit::where('branch_id', $branchId);
        return [
            'arrived' => (clone $q)->where('status', 'arrived')->count(),
            'triaged' => (clone $q)->where('status', 'triaged')->count(),
            'active_alerts' => EmergencyAlert::whereHas('visit', fn ($x) => $x->where('branch_id', $branchId))->where('status', 'active')->count(),
        ];
    }
}
