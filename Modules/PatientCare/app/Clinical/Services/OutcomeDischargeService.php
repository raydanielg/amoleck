<?php

namespace Modules\PatientCare\Clinical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Clinical\Models\ClinicalDischarge;
use Modules\PatientCare\Clinical\Models\ClinicalReassessment;

class OutcomeDischargeService
{
    public const RE_TRANSITIONS = ['draft' => ['pending_review', 'cancelled'], 'pending_review' => ['draft', 'finalized', 'cancelled'], 'finalized' => ['amended'], 'amended' => [], 'cancelled' => []];
    public const DIS_TRANSITIONS = ['draft' => ['pending_review', 'cancelled'], 'pending_review' => ['draft', 'approved', 'cancelled'], 'approved' => ['finalized'], 'finalized' => ['amended'], 'amended' => [], 'cancelled' => []];
    public const DIS_TYPES = ['goals_achieved', 'maximum_improvement', 'treatment_completed', 'patient_discontinued', 'patient_declined', 'referred_facility', 'referred_department', 'clinical_hold', 'other'];
    public const GOAL_REVIEW = ['in_progress', 'improving', 'no_change', 'deteriorating', 'achieved', 'partially_achieved', 'not_achieved', 'discontinued'];

    public function nextNumber(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $row = DB::table('settings')->where('key', "clinical.{$prefix}_sequence")->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            [$table, $col] = $prefix === 'REA' ? ['clinical_reassessments', 'reassessment_number'] : ['clinical_discharges', 'discharge_number'];
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => "clinical.{$prefix}_sequence"],
                ['group' => 'clinical', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    public function transitionReassessment(ClinicalReassessment $r, string $to, int $userId, ?string $reason = null): ClinicalReassessment
    {
        abort_unless(in_array($to, static::RE_TRANSITIONS[$r->status] ?? []), 422);
        DB::transaction(function () use ($r, $to, $userId, $reason) {
            if (in_array($to, ['finalized', 'amended'])) {
                $r->reVersions()->create(['version' => ($r->reVersions()->max('version') ?? 0) + 1, 'snapshot' => $r->load('goalReviews')->toArray(), 'action' => $to, 'acted_by' => $userId, 'reason' => $reason]);
            }
            $r->update(['status' => $to]);
        });
        Audit::record("clinical.reassessment_{$to}", $r->patient, null, ['re' => $r->reassessment_number], $r->branch_id);
        return $r->fresh();
    }

    public function transitionDischarge(ClinicalDischarge $d, string $to, int $userId, ?string $reason = null): ClinicalDischarge
    {
        abort_unless(in_array($to, static::DIS_TRANSITIONS[$d->status] ?? []), 422);
        DB::transaction(function () use ($d, $to, $userId, $reason) {
            if (in_array($to, ['finalized', 'amended'])) {
                $d->versions()->create(['version' => ($d->versions()->max('version') ?? 0) + 1, 'snapshot' => $d->toArray(), 'action' => $to, 'acted_by' => $userId, 'reason' => $reason]);
            }
            $d->update(['status' => $to] + ($to === 'approved' ? ['approved_by' => $userId] : []));
        });
        Audit::record("clinical.discharge_{$to}", $d->patient, null, ['discharge' => $d->discharge_number], $d->branch_id);
        return $d->fresh();
    }

    /** Compare compatible measurements; never treats missing as zero. */
    public function compare($measureId, int $patientId): array
    {
        $rows = \Modules\PatientCare\Clinical\Models\ClinicalOutcomeRecord::where('clinical_outcome_measure_id', $measureId)->where('patient_id', $patientId)->where('status', 'assessed')->orderBy('assessed_at')->get();
        if ($rows->count() < 2) { return ['count' => $rows->count(), 'comparable' => false]; }
        $base = $rows->first(); $latest = $rows->last();
        $sameUnit = $base->unit === $latest->unit;
        return [
            'count' => $rows->count(), 'comparable' => $sameUnit && $base->value_numeric !== null && $latest->value_numeric !== null,
            'baseline' => $base->value_numeric, 'latest' => $latest->value_numeric, 'unit' => $base->unit,
            'abs_change' => $sameUnit && $base->value_numeric !== null ? round($latest->value_numeric - $base->value_numeric, 2) : null,
            'pct_change' => $sameUnit && $base->value_numeric ? round(($latest->value_numeric - $base->value_numeric) / $base->value_numeric * 100, 1) : null,
            'baseline_at' => $base->assessed_at, 'latest_at' => $latest->assessed_at,
        ];
    }
}
