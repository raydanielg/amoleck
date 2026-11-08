<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\RecoveryBed;
use Modules\HospitalOperations\Models\SurgicalHandover;
use Modules\HospitalOperations\Models\SurgicalOperation;
use Modules\HospitalOperations\Models\SurgicalRecoveryRecord;

class SurgicalRecoveryService
{
    public const TRANSITIONS = [
        'awaiting_recovery' => ['received', 'cancelled'],
        'received' => ['under_observation', 'reassessment_required', 'ready_for_review', 'escalated'],
        'under_observation' => ['reassessment_required', 'ready_for_review', 'escalated'],
        'reassessment_required' => ['under_observation', 'ready_for_review', 'escalated'],
        'ready_for_review' => ['cleared_for_transfer', 'under_observation', 'escalated'],
        'cleared_for_transfer' => ['transferred', 'escalated'],
        'escalated' => ['under_observation', 'cleared_for_transfer'],
        'transferred' => [], 'cancelled' => [],
    ];

    private function nextNumber(): string
    {
        return DB::transaction(function () {
            $row = DB::table('settings')->where('key', 'surgery.recovery_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = SurgicalRecoveryRecord::where('record_number', 'like', 'REC-'.date('Y').'-%')->orderByDesc('record_number')->value('record_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => 'surgery.recovery_sequence'], ['group' => 'surgery', 'value' => json_encode($seq), 'is_secret' => false, 'created_at' => now(), 'updated_at' => now()]);
            return sprintf('REC-%s-%06d', date('Y'), $seq);
        });
    }

    /** Open PACU record when operation completes. */
    public function openRecord(SurgicalOperation $op, int $userId): SurgicalRecoveryRecord
    {
        $existing = SurgicalRecoveryRecord::where('surgical_case_id', $op->surgical_case_id)->first();
        if ($existing) {
            return $existing;
        }
        $r = SurgicalRecoveryRecord::create([
            'record_number' => $this->nextNumber(), 'surgical_case_id' => $op->surgical_case_id,
            'surgical_operation_id' => $op->id, 'patient_id' => $op->patient_id, 'branch_id' => $op->branch_id,
            'status' => 'awaiting_recovery', 'created_by' => $userId,
        ]);
        $op->case->statusHistories()->create(['from_status' => 'procedure_completed', 'to_status' => 'recovery', 'reason' => 'PACU record opened', 'changed_by' => $userId]);
        $op->case->update(['status' => 'recovery']);
        app(SurgicalCaseService::class)->alert('recovery.awaiting', $op->case, "Patient awaiting PACU ({$r->record_number}).");
        return $r;
    }

    /** Receive patient into a recovery bed — atomic claim. */
    public function receive(SurgicalRecoveryRecord $r, int $bedId, ?string $handover, int $userId): SurgicalRecoveryRecord
    {
        abort_unless($r->status === 'awaiting_recovery', 422);
        $bed = RecoveryBed::findOrFail($bedId);
        abort_unless((int) $bed->branch_id === (int) $r->branch_id, 422, 'Bed is in another branch.');
        return DB::transaction(function () use ($r, $bed, $handover, $userId) {
            $claimed = RecoveryBed::where('id', $bed->id)->where('status', 'available')->update(['status' => 'occupied']);
            abort_unless($claimed, 422, 'Recovery bed already taken.');
            $r->bedAssignments()->create(['recovery_bed_id' => $bed->id, 'assigned_at' => now(), 'assigned_by' => $userId]);
            $r->update(['recovery_bed_id' => $bed->id, 'arrived_at' => now(), 'received_by' => $userId,
                'anesthesia_handover' => $handover, 'status' => 'received']);
            Audit::record('surgery.recovery_received', $r->patient, null, ['rec' => $r->record_number, 'bed' => $bed->code], $r->branch_id);
            return $r->fresh();
        });
    }

    public function addAssessment(SurgicalRecoveryRecord $r, array $data, int $userId)
    {
        abort_unless(in_array($r->status, ['received', 'under_observation', 'reassessment_required', 'ready_for_review', 'escalated']), 422, "Cannot assess in status {$r->status}.");
        return DB::transaction(function () use ($r, $data, $userId) {
            $a = $r->assessments()->create($data + ['assessed_by' => $userId, 'assessed_at' => now()]);
            $to = match ($data['outcome'] ?? null) {
                'reassessment_required' => 'reassessment_required',
                'ready_for_transfer' => 'ready_for_review',
                'escalated' => 'escalated',
                default => 'under_observation',
            };
            if ($r->status !== $to && in_array($to, self::TRANSITIONS[$r->status])) {
                $r->update(['status' => $to]);
            } elseif ($r->status === 'received') {
                $r->update(['status' => 'under_observation']);
            }
            Audit::record('surgery.recovery_assessed', $r->patient, null, ['rec' => $r->record_number, 'outcome' => $a->outcome], $r->branch_id);
            return $a;
        });
    }

    /** Clinical clearance for transfer — authorized staff only (permission at controller). */
    public function clear(SurgicalRecoveryRecord $r, ?string $disposition, int $userId): SurgicalRecoveryRecord
    {
        abort_unless(in_array($r->status, ['ready_for_review', 'under_observation', 'reassessment_required', 'escalated']), 422, "Cannot clear from {$r->status}.");
        $r->update(['status' => 'cleared_for_transfer', 'cleared_by' => $userId, 'cleared_at' => now(), 'disposition' => $disposition]);
        Audit::record('surgery.recovery_cleared', $r->patient, null, ['rec' => $r->record_number], $r->branch_id);
        return $r->fresh();
    }

    public function releaseBed(RecoveryBed $bed, int $userId): void
    {
        abort_unless($bed->status === 'occupied', 422);
        $bed->update(['status' => 'cleaning', 'cleaning_since' => now()]);
    }

    public function completeCleaning(RecoveryBed $bed, int $userId): void
    {
        abort_unless($bed->status === 'cleaning', 422);
        $bed->update(['status' => 'available', 'cleaning_since' => null]);
    }

    /** Create post-operative handover; completes transfer on acknowledgement. */
    public function createHandover(SurgicalRecoveryRecord $r, array $data, int $userId): SurgicalHandover
    {
        abort_unless($r->status === 'cleared_for_transfer', 422, 'Patient must be cleared for transfer first.');
        abort_if($r->case->handover()->where('status', 'pending')->exists(), 422, 'Handover already pending.');
        $h = $r->case->handover()->create($data + [
            'surgical_operation_id' => $r->surgical_operation_id, 'patient_id' => $r->patient_id,
            'branch_id' => $r->branch_id, 'sent_by' => $userId, 'sent_at' => now(), 'status' => 'pending',
        ]);
        Audit::record('surgery.handover_sent', $r->patient, null, ['dest' => $h->destination_type], $r->branch_id);
        return $h;
    }

    /** Receiving team acknowledgement — this is what completes the transfer. */
    public function acknowledgeHandover(SurgicalHandover $h, int $userId): SurgicalHandover
    {
        abort_unless($h->status === 'pending', 422);
        return DB::transaction(function () use ($h, $userId) {
            $h->update(['status' => 'acknowledged', 'received_by' => $userId, 'received_at' => now()]);
            $r = $h->case->recoveryRecord;
            if ($r) {
                if ($r->recovery_bed_id) {
                    RecoveryBed::where('id', $r->recovery_bed_id)->update(['status' => 'cleaning', 'cleaning_since' => now()]);
                }
                $r->bedAssignments()->whereNull('released_at')->update(['released_at' => now()]);
                $r->update(['status' => 'transferred', 'transferred_at' => now()]);
            }
            $h->case->statusHistories()->create(['from_status' => 'recovery', 'to_status' => 'postoperative_care', 'reason' => 'Handover acknowledged', 'changed_by' => $userId]);
            $h->case->update(['status' => 'postoperative_care']);
            Audit::record('surgery.handover_acknowledged', $h->patient, null, ['dest' => $h->destination_type], $h->branch_id);
            return $h->fresh();
        });
    }
}
