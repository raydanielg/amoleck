<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\ChartOfAccount;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\JournalEntry;
use Modules\Foundation\Support\Audit;

class AccountingService
{
    public const TYPES = ['asset' => 'debit', 'expense' => 'debit', 'liability' => 'credit', 'equity' => 'credit', 'revenue' => 'credit'];

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

    /** Open period covering a date — never post to a closed one. */
    private function periodFor(string $date): \Modules\Finance\Models\AccountingPeriod
    {
        $p = \Modules\Finance\Models\AccountingPeriod::where('starts_at', '<=', $date)->where('ends_at', '>=', $date)->where('status', 'open')->first();
        abort_unless($p, 422, 'No open accounting period.');
        return $p;
    }

    /** Post a balanced journal atomically — unique(source,source_id) idempotent. */
    public function postJournal(string $description, array $lines, int $userId, ?string $source = null, ?int $sourceId = null, ?int $branchId = null, string $type = 'general'): JournalEntry
    {
        $debit = $credit = '0';
        foreach ($lines as $l) {
            abort_unless(in_array($l['direction'], ['debit', 'credit']), 422);
            $acc = ChartOfAccount::findOrFail($l['account_id']);
            abort_unless($acc->posting && $acc->active, 422, "Account {$acc->code} not postable.");
            $l['direction'] === 'debit' ? $debit = bcadd($debit, (string) $l['amount'], 2) : $credit = bcadd($credit, (string) $l['amount'], 2);
        }
        abort_if(bccomp($debit, $credit, 2) !== 0, 422, "Unbalanced journal: D{$debit} ≠ C{$credit}.");
        $period = $this->periodFor(now()->toDateString());

        return DB::transaction(function () use ($description, $lines, $userId, $source, $sourceId, $branchId, $type, $period, $debit) {
            $j = JournalEntry::create([
                'journal_number' => $this->nextNumber('JV', 'journal_entries', 'journal_number'),
                'journal_date' => today(), 'accounting_period_id' => $period->id,
                'branch_id' => $branchId ?? 1, 'type' => $type, 'description' => $description,
                'source' => $source, 'source_id' => $sourceId, 'status' => 'posted',
                'created_by' => $userId, 'posted_by' => $userId, 'posted_at' => now(),
            ]);
            foreach ($lines as $l) { $j->lines()->create($l); }
            Audit::record('finance.journal_posted', null, null, ['jv' => $j->journal_number, 'total' => $debit], $branchId);
            return $j;
        });
    }

    /** Standard postings for financial events — account codes resolved by code, never hardcoded IDs. */
    public function postEvent(string $event, int $sourceId, float $amount, int $branchId, int $userId): ?JournalEntry
    {
        $acc = fn ($code) => ChartOfAccount::where('code', $code)->firstOrFail()->id;
        $lines = match ($event) {
            'invoice_issued' => [
                ['account_id' => $acc('1200'), 'direction' => 'debit', 'amount' => $amount, 'memo' => 'AR'],
                ['account_id' => $acc('4000'), 'direction' => 'credit', 'amount' => $amount, 'memo' => 'Revenue'],
            ],
            'payment_confirmed' => [
                ['account_id' => $acc('1000'), 'direction' => 'debit', 'amount' => $amount, 'memo' => 'Cash'],
                ['account_id' => $acc('1200'), 'direction' => 'credit', 'amount' => $amount, 'memo' => 'AR'],
            ],
            'credit_note' => [
                ['account_id' => $acc('4000'), 'direction' => 'debit', 'amount' => $amount, 'memo' => 'Revenue adj'],
                ['account_id' => $acc('1200'), 'direction' => 'credit', 'amount' => $amount, 'memo' => 'AR'],
            ],
            'refund_paid' => [
                ['account_id' => $acc('2100'), 'direction' => 'debit', 'amount' => $amount, 'memo' => 'Refund liab'],
                ['account_id' => $acc('1000'), 'direction' => 'credit', 'amount' => $amount, 'memo' => 'Cash'],
            ],
            default => null,
        };
        return $lines ? $this->postJournal(ucfirst(str_replace('_', ' ', $event)), $lines, $userId, $event, $sourceId, $branchId) : null;
    }

    public function postExpense(Expense $e, int $userId): JournalEntry
    {
        return $this->postJournal("Expense {$e->expense_number}", [
            ['account_id' => $e->chart_of_account_id ?? ChartOfAccount::where('code', '5000')->firstOrFail()->id, 'direction' => 'debit', 'amount' => $e->amount, 'memo' => $e->category],
            ['account_id' => ChartOfAccount::where('code', '1000')->firstOrFail()->id, 'direction' => 'credit', 'amount' => $e->amount, 'memo' => 'Cash'],
        ], $userId, 'expense', $e->id, $e->branch_id);
    }

    /** Trial balance per account — sum debits/credits on POSTED journals only. */
    public function trialBalance(int $branchId): array
    {
        $rows = JournalEntry::where('branch_id', $branchId)->where('status', 'posted')->join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->selectRaw('chart_of_accounts.code, chart_of_accounts.name, chart_of_accounts.type, SUM(CASE WHEN direction="debit" THEN amount ELSE 0 END) as debit, SUM(CASE WHEN direction="credit" THEN amount ELSE 0 END) as credit')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type')
            ->get();
        return ['accounts' => $rows, 'total_debit' => $rows->sum('debit'), 'total_credit' => $rows->sum('credit'), 'balanced' => bccomp((string) $rows->sum('debit'), (string) $rows->sum('credit'), 2) === 0];
    }

    /** Income statement — revenue credit less expense debit, posted only. */
    public function incomeStatement(int $branchId): array
    {
        $tb = $this->trialBalance($branchId);
        $revenue = (float) $tb['accounts']->where('type', 'revenue')->sum(fn ($a) => $a->credit - $a->debit);
        $expenses = (float) $tb['accounts']->where('type', 'expense')->sum(fn ($a) => $a->debit - $a->credit);
        return ['revenue' => $revenue, 'expenses' => $expenses, 'net' => $revenue - $expenses];
    }

    public function createCoa(array $data): ChartOfAccount
    {
        abort_unless(array_key_exists($data['type'], static::TYPES), 422, 'Invalid account type.');
        if ($data['parent_id'] ?? null) {
            $p = ChartOfAccount::findOrFail($data['parent_id']);
            while ($p) { abort_if($p->id === ($data['id'] ?? 0), 422, 'Circular hierarchy.'); $p = $p->parent; }
        }
        return ChartOfAccount::create($data);
    }
}
