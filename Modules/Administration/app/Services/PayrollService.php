<?php

namespace Modules\Administration\Services;

use Illuminate\Support\Facades\DB;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\EmployeeLoan;
use Modules\Administration\Models\EmployeeSalary;
use Modules\Administration\Models\PayrollPeriod;
use Modules\Administration\Models\PayrollRun;
use Modules\Administration\Models\Payslip;
use Modules\Administration\Models\SalaryComponent;
use Modules\Administration\Models\StatutoryRule;
use Modules\Foundation\Support\Audit;

class PayrollService
{
    public const TRANSITIONS = [
        'draft' => ['calculated', 'cancelled'],
        'calculated' => ['approved'],
        'approved' => ['finalized'],
        'finalized' => ['paid'],
        'paid' => ['closed'],
        'cancelled' => [], 'closed' => [],
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

    /** Effective-dated salary — closes previous record, never overwrites. */
    public function assignSalary(Employee $e, float $basic, string $from, int $userId, ?int $approverId = null): EmployeeSalary
    {
        return DB::transaction(function () use ($e, $basic, $from, $userId, $approverId) {
            $e->salaries()->where('status', 'active')->update(['status' => 'superseded', 'effective_to' => $from]);
            $s = $e->salaries()->create(['basic_salary' => $basic, 'effective_from' => $from, 'status' => 'active', 'approved_by' => $approverId, 'created_by' => $userId]);
            Audit::record('hr.salary_assigned', null, null, ['emp' => $e->employee_number, 'basic' => (string) $basic], $e->primary_branch_id);
            return $s;
        });
    }

    /** Compute a run: basic + earnings − deductions − loan installments; employer separate. */
    public function calculate(Employee $e, PayrollPeriod $p, int $userId): PayrollRun
    {
        abort_unless($e->status === 'active', 422);
        abort_unless($p->status === 'open', 422);
        $salary = $e->salaries()->where('status', 'active')->where('effective_from', '<=', $p->end_date)->latest('effective_from')->first();
        abort_unless($salary, 422, 'No active salary.');

        return DB::transaction(function () use ($e, $p, $salary, $userId) {
            $run = PayrollRun::create([
                'run_number' => $this->nextNumber('PR', 'payroll_runs', 'run_number'),
                'payroll_period_id' => $p->id, 'employee_id' => $e->id, 'branch_id' => $e->primary_branch_id,
                'status' => 'calculated', 'created_by' => $userId,
            ]);
            $basic = (float) $salary->basic_salary;
            $run->lines()->create(['component' => 'BASIC', 'direction' => 'earning', 'amount' => $basic, 'memo' => 'Basic salary']);

            $earnings = $deductions = $employer = (float) $basic;
            $deductions = 0; $employer = 0;
            foreach (SalaryComponent::where('active', true)->get() as $c) {
                $amt = $c->fixed_amount ?? bcmul((string) $basic, bcdiv($c->rate, '100', 4), 2);
                $run->lines()->create(['component' => $c->code, 'direction' => $c->type, 'amount' => $amt, 'memo' => $c->name]);
                match ($c->type) { 'earning' => $earnings = bcadd($earnings, $amt, 2), 'deduction' => $deductions = bcadd($deductions, $amt, 2), 'employer' => $employer = bcadd($employer, $amt, 2), default => null };
            }
            foreach (EmployeeLoan::where('employee_id', $e->id)->where('status', 'active')->get() as $loan) {
                $inst = min((float) $loan->installment, (float) $loan->remaining);
                $run->lines()->create(['component' => 'LOAN-'.$loan->id, 'direction' => 'deduction', 'amount' => $inst, 'memo' => 'Loan installment']);
                $deductions = bcadd($deductions, (string) $inst, 2);
                $loan->decrement('remaining', $inst);
                if (bccomp((string) $loan->fresh()->remaining, '0', 2) <= 0) { $loan->update(['status' => 'settled']); }
            }
            foreach (StatutoryRule::where('effective_from', '<=', $p->end_date)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $p->end_date))->get() as $rule) {
                $amt = bcmul((string) $basic, bcdiv($rule->rate, '100', 4), 2);
                if ($rule->ceiling) { $amt = bccomp($amt, (string) $rule->ceiling, 2) > 0 ? (string) $rule->ceiling : $amt; }
                $run->lines()->create(['component' => $rule->code, 'direction' => $rule->type === 'employer' ? 'employer' : 'deduction', 'amount' => $amt, 'memo' => $rule->code.' @'.$rule->rate.'%']);
                $rule->type === 'employer' ? $employer = bcadd($employer, $amt, 2) : $deductions = bcadd($deductions, $amt, 2);
            }
            $run->update(['gross' => $earnings, 'deductions' => $deductions, 'net' => bcsub($earnings, $deductions, 2), 'employer_contrib' => $employer]);
            Audit::record('hr.payroll_calculated', null, null, ['run' => $run->run_number, 'net' => (string) $run->net], $e->primary_branch_id);
            return $run->fresh();
        });
    }

    public function transition(PayrollRun $r, string $to, int $userId): PayrollRun
    {
        abort_unless(in_array($to, static::TRANSITIONS[$r->status] ?? []), 422, "Cannot move {$r->status}→{$to}.");
        abort_if($to === 'approved' && $r->created_by === $userId, 422, 'Cannot approve own payroll.');
        abort_if($r->status === 'finalized', 422, 'Finalized payroll is immutable.');
        $r->update(['status' => $to] + match ($to) {
            'approved' => ['approved_by' => $userId],
            'finalized' => ['finalized_at' => now()],
            default => [],
        });
        if ($to === 'finalized') { $this->postToGl($r, $userId); $this->issuePayslip($r, $userId); }
        Audit::record("hr.payroll_{$to}", null, null, ['run' => $r->run_number], $r->branch_id);
        return $r->fresh();
    }

    /** Post payroll to Finance GL — Dr salary expense / Cr net payable + statutory liabilities. */
    private function postToGl(PayrollRun $r, int $userId): void
    {
        try {
            $as = app(\Modules\Finance\Services\AccountingService::class);
            $acc = fn ($code) => \Modules\Finance\Models\ChartOfAccount::where('code', $code)->firstOrFail()->id;
            $as->postJournal("Payroll {$r->run_number}", [
                ['account_id' => $acc('5000'), 'direction' => 'debit', 'amount' => $r->gross, 'memo' => 'Salary expense'],
                ['account_id' => $acc('2100'), 'direction' => 'credit', 'amount' => $r->net, 'memo' => 'Net payable'],
                ['account_id' => $acc('2100'), 'direction' => 'credit', 'amount' => $r->deductions, 'memo' => 'Statutory liabilities'],
            ], $userId, 'payroll', $r->id, $r->branch_id);
        } catch (\Throwable) { /* GL integration optional if accounts not yet mapped */ }
    }

    private function issuePayslip(PayrollRun $r, int $userId): void
    {
        $r->payslip()->create(['payslip_number' => $this->nextNumber('PS', 'payslips', 'payslip_number'), 'employee_id' => $r->employee_id, 'status' => 'issued']);
    }

    /** Confirm salary payment → GL Dr net payable / Cr cash. */
    public function markPaid(PayrollRun $r, int $userId): PayrollRun
    {
        abort_unless($r->status === 'finalized', 422);
        $r->update(['status' => 'paid']);
        try {
            $as = app(\Modules\Finance\Services\AccountingService::class);
            $acc = fn ($code) => \Modules\Finance\Models\ChartOfAccount::where('code', $code)->firstOrFail()->id;
            $as->postJournal("Salary paid {$r->run_number}", [
                ['account_id' => $acc('2100'), 'direction' => 'debit', 'amount' => $r->net, 'memo' => 'Net payable cleared'],
                ['account_id' => $acc('1000'), 'direction' => 'credit', 'amount' => $r->net, 'memo' => 'Cash'],
            ], $userId, 'payroll_payment', $r->id, $r->branch_id);
        } catch (\Throwable) {}
        Audit::record('hr.salary_paid', null, null, ['run' => $r->run_number], $r->branch_id);
        return $r->fresh();
    }
}
