<?php

namespace Modules\PatientCare\Clinical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Clinical\Models\ClinicalEncounter;

class ClinicalEncounterService
{
    public const TRANSITIONS = [
        'draft' => ['in_progress', 'pending_review', 'cancelled'],
        'in_progress' => ['pending_review', 'finalized', 'cancelled'],
        'pending_review' => ['in_progress', 'finalized', 'cancelled'],
        'finalized' => ['amended'],
        'amended' => ['finalized'],
        'cancelled' => [],
    ];

    public function nextNumber(): string
    {
        $prefix = \Modules\Foundation\Models\Setting::get('clinical.encounter_prefix', 'ENC');

        return DB::transaction(function () use ($prefix) {
            $row = DB::table('settings')->where('key', 'clinical.encounter_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = ClinicalEncounter::where('encounter_number', 'like', "{$prefix}-".date('Y')."-%")->orderByDesc('encounter_number')->value('encounter_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) {
                $seq = $existing + 1;
            }
            DB::table('settings')->updateOrInsert(['key' => 'clinical.encounter_sequence'],
                ['group' => 'clinical', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);

            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    public function create(array $data, int $userId): ClinicalEncounter
    {
        return DB::transaction(function () use ($data, $userId) {
            $enc = ClinicalEncounter::create($data + ['encounter_number' => $this->nextNumber(), 'created_by' => $userId]);
            $enc->assessment()->create();
            Audit::record('clinical.encounter_created', $enc->patient, null, ['encounter' => $enc->encounter_number, 'type' => $enc->type], $enc->branch_id);
            return $enc;
        });
    }

    public function transition(ClinicalEncounter $enc, string $to, int $userId, ?string $reason = null): ClinicalEncounter
    {
        $from = $enc->status;
        abort_unless(in_array($to, static::TRANSITIONS[$from] ?? []), 422, "Cannot move encounter from {$from} to {$to}.");

        DB::transaction(function () use ($enc, $to, $userId, $reason) {
            if (in_array($to, ['finalized', 'amended'])) {
                $this->snapshot($enc, $to, $userId, $reason);
            }
            $enc->update(['status' => $to, 'updated_by' => $userId]);
        });
        Audit::record("clinical.encounter_{$to}", $enc->patient, ['status' => $from], ['status' => $to], $enc->branch_id);

        return $enc->fresh();
    }

    public function snapshot(ClinicalEncounter $enc, string $action, int $userId, ?string $reason = null): void
    {
        $enc->versions()->create([
            'version' => ($enc->versions()->max('version') ?? 0) + 1,
            'snapshot' => $enc->load(['assessment', 'complaints', 'painAssessments', 'functionalEntries', 'objectiveFindings', 'romMeasurements', 'strengthMeasurements', 'specialTests', 'redFlags', 'problems', 'bodyMapAnnotations'])->toArray(),
            'action' => $action,
            'acted_by' => $userId,
            'reason' => $reason,
        ]);
    }

    public function amend(ClinicalEncounter $enc, array $data, string $reason, int $userId): void
    {
        abort_unless($enc->isFinalized(), 422, 'Only finalized encounters require amendment.');
        DB::transaction(function () use ($enc, $data, $reason, $userId) {
            $this->snapshot($enc, 'amended', $userId, $reason);
            $enc->update(array_filter($data) + ['status' => 'amended', 'updated_by' => $userId]);
        });
        Audit::record('clinical.encounter_amended', $enc->patient, null, ['reason' => $reason], $enc->branch_id);
    }
}
