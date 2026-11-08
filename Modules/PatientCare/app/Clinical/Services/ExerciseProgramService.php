<?php

namespace Modules\PatientCare\Clinical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;

class ExerciseProgramService
{
    public const TRANSITIONS = [
        'draft' => ['pending_review', 'cancelled'],
        'pending_review' => ['draft', 'approved', 'cancelled'],
        'approved' => ['active', 'cancelled'],
        'active' => ['on_hold', 'completed', 'discontinued'],
        'on_hold' => ['active', 'discontinued'],
        'completed' => [], 'discontinued' => [], 'cancelled' => [],
    ];

    public function nextNumber(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $row = DB::table('settings')->where('key', "clinical.{$prefix}_sequence")->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $table = $prefix === 'RX' ? 'clinical_exercise_prescriptions' : 'clinical_home_exercise_programs';
            $col = $prefix === 'RX' ? 'rx_number' : 'hep_number';
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => "clinical.{$prefix}_sequence"],
                ['group' => 'clinical', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    public function version($entity, string $action, int $userId, ?string $reason = null): void
    {
        $type = get_class($entity);
        $v = \Modules\PatientCare\Clinical\Models\ClinicalExerciseVersion::where('entity_type', $type)->where('entity_id', $entity->id)->max('version') ?? 0;
        \Modules\PatientCare\Clinical\Models\ClinicalExerciseVersion::create([
            'entity_type' => $type, 'entity_id' => $entity->id, 'version' => $v + 1,
            'snapshot' => $entity->load(['exercises'])->toArray(), 'action' => $action, 'acted_by' => $userId, 'reason' => $reason,
        ]);
    }

    public function transition($entity, string $to, int $userId, ?string $reason = null)
    {
        abort_unless(in_array($to, static::TRANSITIONS[$entity->status] ?? []), 422, "Cannot move from {$entity->status} to {$to}.");
        abort_if(in_array($to, ['on_hold', 'discontinued', 'cancelled']) && ! $reason, 422, 'Reason required.');
        DB::transaction(function () use ($entity, $to, $userId, $reason) {
            $this->version($entity, $to, $userId, $reason);
            $entity->update(['status' => $to] + ($to === 'approved' ? ['approved_by' => $userId] : []));
        });
        Audit::record('exercise_program.'.$to, $entity->patient, null, ['entity' => $entity->hep_number ?? $entity->rx_number], $entity->branch_id);
    }

    /** Adherence %: completed / expected entries × 100; unreported never counts as non-compliance. */
    public function adherenceRate($program): array
    {
        $rows = $program->adherence()->get();
        $reported = $rows->where('source', '!=', 'expected')->count();
        $completed = $rows->whereIn('status', ['completed', 'partially_completed'])->count();
        return ['reported' => $rows->count(), 'completed' => $completed, 'rate' => $rows->count() ? round($completed / $rows->count() * 100) : null];
    }
}
