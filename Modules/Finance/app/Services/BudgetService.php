<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\BudgetCommitment;
use Modules\Finance\Models\BudgetLine;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\PurchaseRequisition;
use Modules\Foundation\Support\Audit;

class BudgetService
{
    public const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['under_review', 'rejected'],
        'under_review' => ['approved', 'returned', 'rejected'],
        'returned' => ['submitted'],
        'approved' => ['active', 'closed'],
        'active' => ['suspended', 'closed'],
        'suspended' => ['active'],
        'rejected' => [], 'closed' => [],
    ];

    private function nextNumber(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "finance.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'finance', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    public function createBudget(array $data, int $userId): Budget
    {
        $b = Budget::create($data + ['reference' => $this->nextNumber('BDG', 'budgets', 'reference'), 'status' => 'draft', 'created_by' => $userId]);
        Audit::record('finance.budget_created', null, null, ['bdg' => $b->reference]);
        return $b;
    }

    public function addLine(Budget $b, array $data): BudgetLine
    {
        abort_unless($b->status === 'draft', 422);
        $line = $b->lines()->create($data);
        $b->update(['total' => $b->lines()->sum('annual_amount')]);
        return $line;
    }

    public function transition(Budget $b, string $to, int $userId, ?string $reason = null): Budget
    {
        abort_unless(in_array($to, static::TRANSITIONS[$b->status] ?? []), 422, "Cannot move {$b->status}→{$to}.");
        abort_if($to === 'approved' && $b->created_by === $userId, 422, 'Cannot approve own budget.');
        abort_if($to === 'rejected' && ! $reason, 422, 'Reason required.');
        $b->update(['status' => $to, 'reject_reason' => $reason] + ($to === 'approved' ? ['approved_by' => $userId] : []));
        Audit::record("finance.budget_{$to}", null, null, ['bdg' => $b->reference]);
        return $b->fresh();
    }

    /** Available = revised-or-annual − posted expenses on the account − open commitments. */
    public function available(BudgetLine $l): float
    {
        $amount = (float) ($l->revised_amount ?? $l->annual_amount);
        $spent = (float) Expense::where('chart_of_account_id', $l->chart_of_account_id)->where('status', 'posted')->sum('amount');
        $committed = (float) $l->commitments()->where('status', 'approved')->sum('amount');
        return max(0, $amount - $spent - $committed);
    }

    public function utilization(BudgetLine $l): array
    {
        $amount = (float) ($l->revised_amount ?? $l->annual_amount);
        $spent = (float) Expense::where('chart_of_account_id', $l->chart_of_account_id)->where('status', 'posted')->sum('amount');
        $committed = (float) $l->commitments()->where('status', 'approved')->sum('amount');
        return ['budget' => $amount, 'spent' => $spent, 'committed' => $committed, 'available' => $this->available($l), 'utilization' => $amount > 0 ? round($spent / $amount * 100, 1) : 0];
    }

    /** Validate a requisition against available budget — creates commitment only on financial approval. */
    public function validateRequisition(PurchaseRequisition $r, BudgetLine $line, int $userId): PurchaseRequisition
    {
        $available = $this->available($line);
        $status = bccomp((string) $r->estimated_amount, (string) $available, 2) <= 0 ? 'within_budget' : 'insufficient_budget';
        $r->update(['budget_line_id' => $line->id, 'financial_status' => $status]);
        return $r->fresh();
    }

    public function approveRequisition(PurchaseRequisition $r, int $userId): BudgetCommitment
    {
        abort_unless($r->financial_status === 'within_budget' || $r->financial_status === 'override', 422, 'Cannot approve — insufficient budget.');
        return DB::transaction(function () use ($r, $userId) {
            $c = $r->budgetLine->commitments()->create(['reference' => $this->nextNumber('COM', 'budget_commitments', 'reference'), 'amount' => $r->estimated_amount, 'source' => 'requisition', 'source_id' => $r->id, 'created_by' => $userId]);
            $r->update(['financial_status' => 'financially_approved', 'financial_approved_by' => $userId]);
            Audit::record('finance.commitment_created', null, null, ['com' => $c->reference, 'amount' => (string) $r->estimated_amount], $r->branch_id);
            return $c->fresh();
        });
    }

    /** Release a commitment — frees budget back (e.g., cancelled PO). */
    public function releaseCommitment(BudgetCommitment $c, int $userId): void
    {
        abort_unless($c->status === 'approved', 422);
        $c->update(['status' => 'released']);
        Audit::record('finance.commitment_released', null, null, ['com' => $c->reference]);
    }
}
