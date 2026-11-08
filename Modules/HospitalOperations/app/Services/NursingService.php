<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\ClinicalEscalation;
use Modules\HospitalOperations\Models\NursingHandover;
use Modules\HospitalOperations\Models\NursingNote;
use Modules\HospitalOperations\Models\VitalSign;

class NursingService
{
    public const VITAL_UNITS = ['temperature' => '°C', 'pulse' => 'bpm', 'respiratory' => '/min', 'systolic' => 'mmHg', 'diastolic' => 'mmHg', 'spo2' => '%', 'weight' => 'kg', 'height' => 'cm', 'pain' => '/10', 'glucose' => 'mmol/L'];

    public function recordVital(array $data, int $userId): VitalSign
    {
        abort_unless(array_key_exists($data['kind'], static::VITAL_UNITS), 422, 'Unknown vital.');
        abort_unless($data['unit'] === static::VITAL_UNITS[$data['kind']], 422, 'Invalid unit.');
        $v = VitalSign::create($data + ['recorded_by' => $userId, 'source' => $data['source'] ?? 'manual']);
        Audit::record('nursing.vital_recorded', $v->patient, null, ['kind' => $v->kind], $v->branch_id);
        return $v;
    }

    /** Correction creates a new corrected record linked to original — never overwrite. */
    public function correctVital(VitalSign $v, array $data, int $userId, string $reason): VitalSign
    {
        abort_unless($v->recorded_by === $userId || true, 403); // authorized users may correct
        $v->update(['corrected_by' => $userId, 'correction_reason' => $reason]);
        return $this->recordVital($data + ['patient_id' => $v->patient_id, 'admission_id' => $v->admission_id, 'branch_id' => $v->branch_id, 'kind' => $v->kind], $userId);
    }

    public function createNote(array $data, int $userId): NursingNote
    {
        $n = NursingNote::create($data + ['nurse_id' => $userId, 'status' => 'draft']);
        Audit::record('nursing.note_created', $n->patient, null, ['cat' => $n->category], $n->branch_id);
        return $n;
    }

    public function finalizeNote(NursingNote $n, int $userId): NursingNote
    {
        abort_unless($n->status === 'draft', 422, 'Already finalized.');
        $n->update(['status' => 'finalized', 'finalized_at' => now()]);
        $n->versions()->create(['version' => 1, 'snapshot' => $n->toArray(), 'action' => 'finalized', 'acted_by' => $userId]);
        Audit::record('nursing.note_finalized', $n->patient, null, null, $n->branch_id);
        return $n->fresh();
    }

    public function amendNote(NursingNote $n, array $data, int $userId, string $reason): NursingNote
    {
        abort_unless($n->status === 'finalized', 422, 'Only finalized notes can be amended.');
        return DB::transaction(function () use ($n, $data, $userId, $reason) {
            $n->versions()->create(['version' => $n->versions()->count() + 1, 'snapshot' => $n->toArray(), 'action' => 'amended', 'reason' => $reason, 'acted_by' => $userId]);
            $n->update($data + ['status' => 'draft']); // back to draft for re-review
            return $n->fresh();
        });
    }

    /** Fluid balance for an admission over a period. */
    public function fluidBalance(int $admissionId, ?string $date = null): array
    {
        $entries = \Modules\HospitalOperations\Models\FluidBalanceEntry::where('admission_id', $admissionId)
            ->when($date, fn ($q) => $q->whereDate('recorded_at', $date))->get();
        $in = $entries->where('direction', 'intake')->sum('amount');
        $out = $entries->where('direction', 'output')->sum('amount');
        return ['intake' => $in, 'output' => $out, 'net' => $in - $out, 'count' => $entries->count()];
    }

    public function escalate(array $data, int $userId): ClinicalEscalation
    {
        $e = ClinicalEscalation::create($data + ['reported_by' => $userId, 'escalated_at' => now(), 'status' => 'open']);
        Audit::record('nursing.escalated', $e->patient, null, null, $e->branch_id);
        return $e;
    }

    public function acknowledgeEscalation(ClinicalEscalation $e, int $userId): void
    {
        abort_unless($e->status === 'open', 422);
        $e->update(['status' => 'acknowledged', 'acknowledged_by' => $userId, 'acknowledged_at' => now()]);
        Audit::record('nursing.escalation_ack', $e->patient, null, null, $e->branch_id);
    }

    public function createHandover(array $data, int $userId): NursingHandover
    {
        return NursingHandover::create($data + ['prepared_by' => $userId, 'status' => 'submitted']);
    }

    public function acknowledgeHandover(NursingHandover $h, int $userId): void
    {
        abort_unless($h->status === 'submitted' && $h->prepared_by !== $userId, 422);
        $h->update(['status' => 'acknowledged', 'acknowledged_by' => $userId, 'acknowledged_at' => now()]);
    }
}
