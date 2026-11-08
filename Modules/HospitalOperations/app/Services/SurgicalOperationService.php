<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\AnesthesiaRecord;
use Modules\HospitalOperations\Models\MedicationAdministration;
use Modules\HospitalOperations\Models\OperativeNote;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalOperation;

class SurgicalOperationService
{
    public const NOTE_TRANSITIONS = [
        'draft' => ['in_progress', 'pending_review', 'finalized'],
        'in_progress' => ['pending_review', 'finalized'],
        'pending_review' => ['finalized', 'in_progress'],
        'finalized' => ['amended'],
        'amended' => [],
    ];

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

    /** Start an operation: requires sign_in + time_out verification (or documented exceptions). */
    public function start(SurgicalCase $c, int $userId, ?int $bookingId = null): SurgicalOperation
    {
        abort_unless(in_array($c->status, ['scheduled', 'preoperative_preparation', 'ready_for_theatre']), 422, "Case status {$c->status} cannot enter theatre.");
        abort_if($c->operations()->where('status', 'in_progress')->exists(), 422, 'Operation already in progress.');
        $booking = $bookingId
            ? $c->bookings()->where('status', 'scheduled')->findOrFail($bookingId)
            : $c->bookings()->where('status', 'scheduled')->latest()->first();
        abort_if($bookingId === null && $c->priority !== 'emergency' && ! $booking, 422, 'No scheduled booking.');

        $safety = app(SurgicalSafetyService::class);
        abort_unless($safety->stageDone($c, 'sign_in') || $c->safetyExceptions()->where('stage', 'sign_in')->exists(), 422, 'Sign In verification required.');
        abort_unless($safety->stageDone($c, 'time_out') || $c->safetyExceptions()->where('stage', 'time_out')->exists(), 422, 'Time Out verification required.');

        return DB::transaction(function () use ($c, $booking, $userId) {
            $op = SurgicalOperation::create([
                'operation_number' => $this->nextNumber('surgery.operation_sequence', 'OPS', 'surgical_operations', 'operation_number'),
                'surgical_case_id' => $c->id, 'surgical_booking_id' => $booking?->id,
                'operating_theatre_id' => $booking?->operating_theatre_id,
                'patient_id' => $c->patient_id, 'branch_id' => $c->branch_id,
                'surgeon_id' => $booking?->surgeon_id ?? $c->surgeon_id,
                'anesthetist_id' => $booking?->anesthetist_id,
                'status' => 'in_progress', 'patient_in_at' => now(), 'created_by' => $userId,
            ]);
            $op->statusHistories()->create(['to_status' => 'in_progress', 'changed_by' => $userId]);
            if ($booking) {
                $booking->update(['status' => 'in_progress']);
                $booking->histories()->create(['action' => 'started', 'changed_by' => $userId]);
                $booking->reservations()->where('status', 'active')->update(['status' => 'consumed']);
                if ($t = $booking->theatre) {
                    $t->statusHistories()->create(['from_status' => $t->status, 'to_status' => 'occupied', 'reason' => "Operation {$op->operation_number}", 'changed_by' => $userId]);
                    $t->update(['status' => 'occupied']);
                }
            }
            $c->statusHistories()->create(['from_status' => $c->status, 'to_status' => 'in_theatre', 'reason' => "Operation {$op->operation_number} started", 'changed_by' => $userId]);
            $c->update(['status' => 'in_theatre']);
            Audit::record('surgery.operation_started', $c->patient, null, ['op' => $op->operation_number], $c->branch_id);
            app(SurgicalCaseService::class)->alert('operation.started', $c, "Operation {$op->operation_number} started for case {$c->case_number}.");
            return $op->fresh();
        });
    }

    /** Record actual performed procedure(s) — distinct from the planned procedure. */
    public function recordProcedure(SurgicalOperation $op, array $data, int $userId)
    {
        abort_unless($op->status === 'in_progress', 422, 'Operation not in progress.');
        abort_unless(in_array($data['kind'] ?? 'performed', ['planned', 'performed', 'additional', 'changed', 'aborted', 'incomplete']), 422);
        $p = $op->procedures()->create($data + ['sort_order' => $op->procedures()->count()]);
        Audit::record('surgery.procedure_recorded', $op->patient, null, ['op' => $op->operation_number, 'kind' => $p->kind], $op->branch_id);
        return $p;
    }

    /** Complete the operation: requires sign_out + actual procedure record. */
    public function complete(SurgicalOperation $op, array $data, int $userId): SurgicalOperation
    {
        abort_unless($op->status === 'in_progress', 422);
        $c = $op->case;
        $safety = app(SurgicalSafetyService::class);
        abort_unless($safety->stageDone($c, 'sign_out') || $c->safetyExceptions()->where('stage', 'sign_out')->exists(), 422, 'Sign Out verification required.');
        abort_unless($op->procedures()->whereIn('kind', ['performed', 'additional', 'changed', 'incomplete'])->exists(), 422, 'Record at least one performed procedure.');
        abort_if($op->counts()->where('status', 'discrepancy')->exists(), 422, 'Unresolved count discrepancy.');

        return DB::transaction(function () use ($op, $c, $data, $userId) {
            $op->update(array_filter([
                'procedure_end_at' => $data['procedure_end_at'] ?? now(), 'patient_out_at' => $data['patient_out_at'] ?? now(),
                'anesthesia_end_at' => $data['anesthesia_end_at'] ?? null,
                'preop_diagnosis' => $data['preop_diagnosis'] ?? null, 'postop_diagnosis' => $data['postop_diagnosis'] ?? null,
                'findings' => $data['findings'] ?? null, 'narrative' => $data['narrative'] ?? null,
                'complications' => $data['complications'] ?? null, 'unexpected_events' => $data['unexpected_events'] ?? null,
                'estimated_blood_loss_ml' => $data['estimated_blood_loss_ml'] ?? null,
                'outcome' => $data['outcome'] ?? null, 'postoperative_destination' => $data['postoperative_destination'] ?? 'pacu',
                'status' => 'completed',
            ], fn ($v) => ! is_null($v)));
            $op->statusHistories()->create(['from_status' => 'in_progress', 'to_status' => 'completed', 'changed_by' => $userId]);
            if ($op->booking) {
                $op->booking->update(['status' => 'completed']);
                $op->booking->histories()->create(['action' => 'completed', 'changed_by' => $userId]);
            }
            if ($t = $op->theatre) {
                $t->statusHistories()->create(['from_status' => 'occupied', 'to_status' => 'cleaning', 'reason' => "Operation {$op->operation_number} completed", 'changed_by' => $userId]);
                $t->update(['status' => 'cleaning']);
            }
            $c->statusHistories()->create(['from_status' => 'in_theatre', 'to_status' => 'procedure_completed', 'changed_by' => $userId]);
            $c->update(['status' => 'procedure_completed']);
            // Open PACU record when destination involves recovery
            if (in_array($op->postoperative_destination, ['pacu', 'ward', 'surgical_ward', 'icu', 'hdu', null])) {
                app(SurgicalRecoveryService::class)->openRecord($op, $userId);
            }
            Audit::record('surgery.operation_completed', $op->patient, null, ['op' => $op->operation_number], $op->branch_id);
            app(SurgicalCaseService::class)->alert('operation.completed', $c, "Operation {$op->operation_number} completed.");
            return $op->fresh();
        });
    }

    public function abort(SurgicalOperation $op, string $reason, int $userId): SurgicalOperation
    {
        abort_unless($op->status === 'in_progress', 422);
        abort_if(! $reason, 422, 'Reason required.');
        return DB::transaction(function () use ($op, $reason, $userId) {
            $op->update(['status' => 'aborted', 'procedure_end_at' => now(), 'patient_out_at' => now(), 'unexpected_events' => $reason]);
            $op->statusHistories()->create(['from_status' => 'in_progress', 'to_status' => 'aborted', 'reason' => $reason, 'changed_by' => $userId]);
            if ($op->booking) {
                $op->booking->update(['status' => 'completed']);
            }
            if ($t = $op->theatre) {
                $t->statusHistories()->create(['from_status' => 'occupied', 'to_status' => 'cleaning', 'reason' => "Operation {$op->operation_number} aborted", 'changed_by' => $userId]);
                $t->update(['status' => 'cleaning']);
            }
            Audit::record('surgery.operation_aborted', $op->patient, null, ['op' => $op->operation_number, 'reason' => $reason], $op->branch_id);
            return $op->fresh();
        });
    }

    // ---- Operative notes (versioned, finalized = immutable) ----
    public function saveNote(SurgicalOperation $op, array $data, int $userId): OperativeNote
    {
        return DB::transaction(function () use ($op, $data, $userId) {
            $note = $op->operativeNote;
            if ($note) {
                abort_if(in_array($note->status, ['finalized']), 422, 'Finalized note is immutable; use amend.');
                $note->versions()->create(['version' => $note->version, 'snapshot' => $note->toArray(), 'action' => 'updated', 'acted_by' => $userId]);
                $note->update($data + ['version' => $note->version + 1, 'status' => $data['status'] ?? $note->status]);
            } else {
                $note = $op->operativeNote()->create($data + ['surgical_case_id' => $op->surgical_case_id, 'author_id' => $userId, 'status' => $data['status'] ?? 'draft']);
                $note->versions()->create(['version' => 1, 'snapshot' => $note->toArray(), 'action' => 'created', 'acted_by' => $userId]);
            }
            Audit::record('surgery.opnote_saved', $op->patient, null, ['note' => $note->id, 'version' => $note->version], $op->branch_id);
            return $note->fresh();
        });
    }

    public function transitionNote(OperativeNote $note, string $to, int $userId): OperativeNote
    {
        $from = $note->status;
        abort_unless(in_array($to, self::NOTE_TRANSITIONS[$from] ?? []), 422, "Cannot move note {$from}→{$to}.");
        abort_if($to === 'finalized' && $note->author_id !== $userId && $note->operation->surgeon_id !== $userId, 422, 'Only the author/surgeon can finalize.');
        DB::transaction(function () use ($note, $from, $to, $userId) {
            $note->versions()->create(['version' => $note->version, 'snapshot' => $note->toArray(), 'action' => "status_{$to}", 'acted_by' => $userId]);
            $note->update(['status' => $to, 'version' => $note->version + 1] + ($to === 'finalized' ? ['reviewed_by' => $userId, 'finalized_at' => now()] : []));
        });
        Audit::record("surgery.opnote_{$to}", $note->case->patient, ['status' => $from], ['status' => $to], $note->case->branch_id);
        return $note->fresh();
    }

    /** Controlled amendment — preserves the finalized version in history. */
    public function amendNote(OperativeNote $note, array $data, string $reason, int $userId): OperativeNote
    {
        abort_unless($note->status === 'finalized', 422, 'Only finalized notes are amended.');
        abort_if(! $reason, 422, 'Amendment reason required.');
        return DB::transaction(function () use ($note, $data, $reason, $userId) {
            $note->versions()->create(['version' => $note->version, 'snapshot' => $note->toArray(), 'action' => 'amended: '.$reason, 'acted_by' => $userId]);
            $note->update($data + ['version' => $note->version + 1, 'status' => 'amended']);
            Audit::record('surgery.opnote_amended', $note->case->patient, ['version' => $note->version - 1], ['version' => $note->version, 'reason' => $reason], $note->case->branch_id);
            return $note->fresh();
        });
    }

    // ---- Intra-operative anesthesia record (eMAR remains canonical for medications) ----
    public function saveAnesthesiaRecord(SurgicalOperation $op, array $data, int $userId): AnesthesiaRecord
    {
        return DB::transaction(function () use ($op, $data, $userId) {
            $r = $op->anesthesiaRecord;
            if ($r) {
                abort_if($r->status === 'closed', 422, 'Closed record.');
                $r->update($data + ['version' => $r->version + 1]);
            } else {
                $r = $op->anesthesiaRecord()->create($data + [
                    'surgical_case_id' => $op->surgical_case_id, 'patient_id' => $op->patient_id,
                    'anesthetist_id' => $data['anesthetist_id'] ?? $userId, 'started_at' => $data['started_at'] ?? now(),
                ]);
            }
            Audit::record('surgery.anesthesia_saved', $op->patient, null, ['op' => $op->operation_number], $op->branch_id);
            return $r->fresh();
        });
    }

    /** Reference an existing eMAR administration — never creates medication records. */
    public function linkMedication(AnesthesiaRecord $r, int $administrationId, ?string $notes, int $userId): void
    {
        $adm = MedicationAdministration::findOrFail($administrationId);
        abort_unless((int) $adm->patient_id === (int) $r->patient_id, 422, 'Administration belongs to another patient.');
        $r->medicationRefs()->firstOrCreate(['medication_administration_id' => $adm->id], ['notes' => $notes]);
        Audit::record('surgery.anesthesia_med_linked', $r->operation->patient, null, ['adm' => $adm->id], $r->operation->branch_id);
    }

    public function closeAnesthesiaRecord(AnesthesiaRecord $r, int $userId): AnesthesiaRecord
    {
        abort_unless($r->status === 'open', 422);
        $r->update(['status' => 'closed', 'ended_at' => $r->ended_at ?? now()]);
        Audit::record('surgery.anesthesia_closed', $r->operation->patient, null, ['op' => $r->surgical_operation_id], $r->operation->branch_id);
        return $r->fresh();
    }
}
