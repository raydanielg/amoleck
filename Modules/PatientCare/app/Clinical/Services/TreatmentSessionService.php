<?php

namespace Modules\PatientCare\Clinical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentSession;

class TreatmentSessionService
{
    public const TRANSITIONS = [
        'draft' => ['in_progress', 'pending_review', 'cancelled'],
        'in_progress' => ['pending_review', 'finalized', 'cancelled'],
        'pending_review' => ['in_progress', 'finalized', 'cancelled'],
        'finalized' => ['amended'],
        'amended' => [],
        'cancelled' => [],
    ];

    public const PERMS = [
        'in_progress' => 'patientcare.clinical.sessions.update',
        'pending_review' => 'patientcare.clinical.sessions.submit',
        'finalized' => 'patientcare.clinical.sessions.finalize',
        'amended' => 'patientcare.clinical.sessions.amend',
        'cancelled' => 'patientcare.clinical.sessions.cancel',
    ];

    public function nextNumber(): string
    {
        $prefix = \Modules\Foundation\Models\Setting::get('clinical.session_prefix', 'SES');

        return DB::transaction(function () use ($prefix) {
            $row = DB::table('settings')->where('key', 'clinical.session_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = ClinicalTreatmentSession::where('session_number', 'like', "{$prefix}-".date('Y')."-%")->orderByDesc('session_number')->value('session_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) {
                $seq = $existing + 1;
            }
            DB::table('settings')->updateOrInsert(['key' => 'clinical.session_sequence'],
                ['group' => 'clinical', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);

            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    public function create(array $data, int $userId): ClinicalTreatmentSession
    {
        return DB::transaction(function () use ($data, $userId) {
            $s = ClinicalTreatmentSession::create($data + ['session_number' => $this->nextNumber(), 'created_by' => $userId]);
            $s->notes()->create(['template' => 'soap']);
            Audit::record('clinical.session_created', $s->patient, null, ['session' => $s->session_number], $s->branch_id);
            return $s;
        });
    }

    public function transition(ClinicalTreatmentSession $s, string $to, int $userId, ?string $reason = null): ClinicalTreatmentSession
    {
        $from = $s->status;
        abort_unless(in_array($to, static::TRANSITIONS[$from] ?? []), 422, "Cannot move session from {$from} to {$to}.");
        abort_if($to === 'cancelled' && ! $reason, 422, 'Cancellation requires a reason.');

        DB::transaction(function () use ($s, $to, $from, $userId, $reason) {
            if (in_array($to, ['finalized', 'amended'])) {
                $this->snapshot($s, $to, $userId, $reason);
            }
            if ($to === 'finalized' || $from === 'pending_review' && $to === 'in_progress') {
                $s->reviews()->create(['reviewer_id' => $userId, 'decision' => $to === 'finalized' ? 'approved' : 'returned', 'reason' => $reason]);
            }
            $s->update(['status' => $to, 'treating_physiotherapist_id' => $s->treating_physiotherapist_id ?? $userId]);
        });
        Audit::record("clinical.session_{$to}", $s->patient, ['status' => $from], ['status' => $to], $s->branch_id);

        return $s->fresh();
    }

    public function snapshot(ClinicalTreatmentSession $s, string $action, int $userId, ?string $reason = null): void
    {
        $s->versions()->create([
            'version' => ($s->versions()->max('version') ?? 0) + 1,
            'snapshot' => $s->load(['interventions', 'notes', 'adverseEvents'])->toArray(),
            'action' => $action,
            'acted_by' => $userId,
            'reason' => $reason,
        ]);
    }
}
