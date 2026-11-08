<?php

namespace Modules\PatientCare\Clinical\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan;

class TreatmentPlanService
{
    public const TRANSITIONS = [
        'draft' => ['pending_review', 'approved', 'cancelled'],
        'pending_review' => ['draft', 'approved', 'cancelled'],
        'approved' => ['active', 'cancelled'],
        'active' => ['on_hold', 'completed', 'discontinued'],
        'on_hold' => ['active', 'discontinued'],
        'completed' => [],
        'discontinued' => [],
        'cancelled' => [],
    ];

    public const PERMS = [
        'pending_review' => 'patientcare.treatment_plans.submit',
        'draft' => 'patientcare.treatment_plans.update',
        'approved' => 'patientcare.treatment_plans.approve',
        'active' => 'patientcare.treatment_plans.activate',
        'on_hold' => 'patientcare.treatment_plans.hold',
        'completed' => 'patientcare.treatment_plans.update',
        'discontinued' => 'patientcare.treatment_plans.discontinue',
        'cancelled' => 'patientcare.treatment_plans.review',
    ];

    public const REQUIRES_REASON = ['on_hold', 'discontinued', 'cancelled'];

    public function nextNumber(): string
    {
        $prefix = \Modules\Foundation\Models\Setting::get('clinical.plan_prefix', 'TPL');

        return DB::transaction(function () use ($prefix) {
            $row = DB::table('settings')->where('key', 'clinical.plan_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = ClinicalTreatmentPlan::where('plan_number', 'like', "{$prefix}-".date('Y')."-%")->orderByDesc('plan_number')->value('plan_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) {
                $seq = $existing + 1;
            }
            DB::table('settings')->updateOrInsert(['key' => 'clinical.plan_sequence'],
                ['group' => 'clinical', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);

            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    public function create(array $data, int $userId): ClinicalTreatmentPlan
    {
        return DB::transaction(function () use ($data, $userId) {
            $plan = ClinicalTreatmentPlan::create($data + ['plan_number' => $this->nextNumber(), 'created_by' => $userId]);
            $this->snapshot($plan, 'created', $userId);
            Audit::record('treatment_plan.created', $plan->patient, null, ['plan' => $plan->plan_number], $plan->branch_id);
            return $plan;
        });
    }

    public function transition(ClinicalTreatmentPlan $plan, string $to, int $userId, ?string $reason = null, ?string $comments = null): ClinicalTreatmentPlan
    {
        abort_unless(in_array($to, static::TRANSITIONS[$plan->status] ?? []), 422, "Cannot move plan from {$plan->status} to {$to}.");
        abort_if(in_array($to, static::REQUIRES_REASON) && ! $reason, 422, ucfirst(str_replace('_', ' ', $to)).' requires a reason.');

        $from = $plan->status;
        DB::transaction(function () use ($plan, $to, $userId, $reason, $comments) {
            $this->snapshot($plan, $to, $userId, $reason);
            if ($to === 'approved') {
                $plan->update(['approved_by' => $userId, 'approved_at' => now()]);
            }
            if (in_array($to, ['approved', 'cancelled'])) {
                $plan->reviews()->create(['reviewer_id' => $userId, 'decision' => $to === 'approved' ? 'approved' : 'rejected', 'comments' => $comments, 'rejection_reason' => $to === 'cancelled' ? $reason : null, 'version' => $plan->versions()->max('version')]);
            }
            $plan->update(['status' => $to]);
        });
        Audit::record("treatment_plan.{$to}", $plan->patient, ['status' => $from], ['status' => $to, 'reason' => $reason], $plan->branch_id);

        return $plan->fresh();
    }

    public function snapshot(ClinicalTreatmentPlan $plan, string $action, int $userId, ?string $reason = null): void
    {
        $plan->versions()->create([
            'version' => ($plan->versions()->max('version') ?? 0) + 1,
            'snapshot' => $plan->load(['problems', 'goals', 'plannedInterventions', 'precautionRecords'])->toArray(),
            'action' => $action,
            'acted_by' => $userId,
            'reason' => $reason,
        ]);
    }
}
