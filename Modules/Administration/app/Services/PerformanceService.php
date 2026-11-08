<?php

namespace Modules\Administration\Services;

use Illuminate\Support\Facades\DB;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\EmployeeObjective;
use Modules\Administration\Models\PerformanceCycle;
use Modules\Administration\Models\PerformanceImprovementPlan;
use Modules\Administration\Models\PerformanceReview;
use Modules\Foundation\Support\Audit;

class PerformanceService
{
    public const TRANSITIONS = [
        'draft' => ['self_submitted', 'cancelled'],
        'self_submitted' => ['supervisor_reviewed'],
        'supervisor_reviewed' => ['hr_approved', 'returned'],
        'hr_approved' => ['finalized'],
        'returned' => ['self_submitted'],
        'finalized' => [], 'cancelled' => [],
    ];

    private function nextNumber(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "hr.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'hr', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    public function createCycle(array $data): PerformanceCycle
    {
        return PerformanceCycle::create($data + ['status' => 'draft']);
    }

    public function assignObjective(Employee $e, int $kpiId, array $data, int $userId): EmployeeObjective
    {
        return $e->objectives()->create($data + ['performance_kpi_id' => $kpiId, 'status' => 'assigned']);
    }

    public function openReview(Employee $e, PerformanceCycle $c, int $userId): PerformanceReview
    {
        return PerformanceReview::firstOrCreate(
            ['employee_id' => $e->id, 'performance_cycle_id' => $c->id],
            ['review_number' => $this->nextNumber('PRV', 'performance_reviews', 'review_number'), 'status' => 'draft', 'created_by' => $userId]
        );
    }

    public function submitSelf(PerformanceReview $r, float $score, ?string $comments, int $userId): PerformanceReview
    {
        abort_unless($r->status === 'draft', 422);
        abort_unless($score >= 1 && $score <= 5, 422, 'Score out of range.');
        $r->update(['self_score' => $score, 'comments' => $comments, 'status' => 'self_submitted']);
        Audit::record('hr.self_assessment', null, null, ['rev' => $r->review_number]);
        return $r->fresh();
    }

    /** Supervisor scores → final = 40% self + 60% supervisor (configurable weights could vary). */
    public function supervisorReview(PerformanceReview $r, float $score, int $userId, ?string $comments = null): PerformanceReview
    {
        abort_unless($r->status === 'self_submitted', 422);
        abort_unless($score >= 1 && $score <= 5, 422, 'Score out of range.');
        abort_if($r->employee->user_id === $userId, 422, 'Cannot review own self-assessment.');
        $final = round($r->self_score * 0.4 + $score * 0.6, 2);
        $r->update(['supervisor_score' => $score, 'final_score' => $final, 'reviewer_id' => $userId, 'comments' => $comments ?? $r->comments, 'status' => 'supervisor_reviewed']);
        Audit::record('hr.supervisor_review', null, null, ['rev' => $r->review_number, 'score' => $final]);
        return $r->fresh();
    }

    public function transition(PerformanceReview $r, string $to, int $userId): PerformanceReview
    {
        abort_unless(in_array($to, static::TRANSITIONS[$r->status] ?? []), 422, "Cannot move {$r->status}→{$to}.");
        abort_if($to === 'finalized' && $r->final_score === null, 422, 'Cannot finalize without a score.');
        $r->update(['status' => $to] + ($to === 'hr_approved' ? ['approved_by' => $userId] : []));
        Audit::record("hr.review_{$to}", null, null, ['rev' => $r->review_number]);
        return $r->fresh();
    }

    /** Employee acknowledges — may add a disagreement (receipt ≠ agreement). */
    public function acknowledge(PerformanceReview $r, ?string $disagreement): PerformanceReview
    {
        abort_unless($r->status === 'finalized', 422);
        $r->update(['disagreement' => $disagreement]);
        Audit::record('hr.review_acknowledged', null, null, ['rev' => $r->review_number]);
        return $r->fresh();
    }

    public function createPip(Employee $e, ?int $reviewId, array $data, int $userId): PerformanceImprovementPlan
    {
        $pip = PerformanceImprovementPlan::create($data + ['pip_number' => $this->nextNumber('PIP', 'performance_improvement_plans', 'pip_number'), 'employee_id' => $e->id, 'performance_review_id' => $reviewId, 'status' => 'draft', 'created_by' => $userId]);
        Audit::record('hr.pip_created', null, null, ['pip' => $pip->pip_number]);
        return $pip;
    }

    public function dashboard(int $branchId): array
    {
        $q = PerformanceReview::whereHas('employee', fn ($x) => $x->where('primary_branch_id', $branchId));
        return [
            'reviews_total' => (clone $q)->count(),
            'pending_self' => (clone $q)->where('status', 'draft')->count(),
            'pending_supervisor' => (clone $q)->where('status', 'self_submitted')->count(),
            'pending_hr' => (clone $q)->where('status', 'supervisor_reviewed')->count(),
            'finalized' => (clone $q)->where('status', 'finalized')->count(),
            'active_pips' => PerformanceImprovementPlan::whereHas('employee', fn ($x) => $x->where('primary_branch_id', $branchId))->where('status', 'active')->count(),
        ];
    }
}
