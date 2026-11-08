<?php

namespace Modules\Administration\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\LeaveBalanceTransaction;
use Modules\Administration\Models\LeaveRequest;
use Modules\Administration\Models\LeaveType;
use Modules\Administration\Models\PublicHoliday;
use Modules\Foundation\Support\Audit;

class LeaveService
{
    public const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'rejected', 'withdrawn'],
        'approved' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['returned_to_work'],
        'rejected' => [], 'cancelled' => [], 'withdrawn' => [], 'returned_to_work' => [],
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

    /** Working days in range — weekdays minus public holidays (per branch + global). */
    public function workingDays(string $start, string $end, int $branchId): int
    {
        $holidays = PublicHoliday::where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->whereBetween('date', [$start, $end])->pluck('date')->map(fn ($d) => $d->toDateString());
        $days = 0;
        $cur = Carbon::parse($start);
        while ($cur->lte(Carbon::parse($end))) {
            if (! $cur->isWeekend() && ! $holidays->contains($cur->toDateString())) { $days++; }
            $cur->addDay();
        }
        return $days;
    }

    /** Balance from the ledger — never a stored field. */
    public function balance(int $employeeId, int $typeId): float
    {
        return (float) LeaveBalanceTransaction::where('employee_id', $employeeId)->where('leave_type_id', $typeId)->sum('days');
    }

    private function ledger(Employee $e, LeaveType $lt, string $type, float $days, int $userId, ?string $ref = null, ?string $reason = null): void
    {
        LeaveBalanceTransaction::create(['employee_id' => $e->id, 'leave_type_id' => $lt->id, 'type' => $type, 'days' => $days, 'reference' => $ref, 'reason' => $reason, 'created_by' => $userId]);
    }

    /** Post accrual — idempotent via reference. */
    public function accrue(Employee $e, LeaveType $lt, float $days, int $userId, string $ref): void
    {
        abort_if(LeaveBalanceTransaction::where('employee_id', $e->id)->where('leave_type_id', $lt->id)->where('reference', $ref)->exists(), 422, 'Accrual already posted.');
        $this->ledger($e, $lt, 'accrual', $days, $userId, $ref);
        Audit::record('hr.leave_accrued', null, null, ['emp' => $e->employee_number, 'type' => $lt->code, 'days' => $days]);
    }

    public function request(array $data, int $userId): LeaveRequest
    {
        $e = Employee::findOrFail($data['employee_id']);
        abort_unless($e->status === 'active', 422, 'Only active employees can request leave.');
        $lt = LeaveType::findOrFail($data['leave_type_id']);
        abort_unless($lt->active, 422);
        $days = $this->workingDays($data['start_date'], $data['end_date'], $data['branch_id']);
        abort_if($days <= 0, 422, 'No working days in range.');
        abort_if($lt->max_days && $days > $lt->max_days, 422, "Exceeds {$lt->max_days}-day limit.");
        $r = LeaveRequest::create($data + ['request_number' => $this->nextNumber('LR', 'leave_requests', 'request_number'), 'days' => $days, 'status' => 'draft', 'created_by' => $userId]);
        Audit::record('hr.leave_requested', null, null, ['lr' => $r->request_number, 'days' => $days], $r->branch_id);
        return $r;
    }

    public function transition(LeaveRequest $r, string $to, int $userId, ?string $reason = null): LeaveRequest
    {
        abort_unless(in_array($to, static::TRANSITIONS[$r->status] ?? []), 422, "Cannot move {$r->status}→{$to}.");
        abort_if($to === 'approved' && $r->created_by === $userId, 422, 'Cannot approve own request.');
        abort_if($to === 'rejected' && ! $reason, 422, 'Reason required.');

        return DB::transaction(function () use ($r, $to, $userId, $reason) {
            $e = $r->employee; $lt = $r->leaveType;
            match ($to) {
                'submitted' => $this->ledger($e, $lt, 'reservation', -$r->days, $userId, $r->request_number, 'Reserved'),
                'approved' => $this->ledger($e, $lt, 'use', 0, $userId, $r->request_number, 'Approved — reservation becomes use'),
                'rejected', 'withdrawn', 'cancelled' => $this->ledger($e, $lt, 'release', $r->days, $userId, $r->request_number, 'Released'),
                default => null,
            };
            abort_if($to === 'submitted' && $this->balance($e->id, $lt->id) < 0, 422, 'Insufficient balance.');
            $r->update(['status' => $to] + match ($to) {
                'approved', 'rejected' => ['reviewed_by' => $userId, 'reviewed_at' => now(), 'reject_reason' => $reason],
                default => [],
            });
            Audit::record("hr.leave_{$to}", null, null, ['lr' => $r->request_number], $r->branch_id);
            return $r->fresh();
        });
    }

    /** On-leave employees for absence detection (M2 integration). */
    public function onLeaveToday(int $branchId): \Illuminate\Support\Collection
    {
        return LeaveRequest::where('branch_id', $branchId)->whereIn('status', ['approved', 'in_progress'])
            ->where('start_date', '<=', today())->where('end_date', '>=', today())
            ->with('employee:id,employee_number,first_name,last_name')->get();
    }

    /** Return-to-work record. */
    public function recordReturn(LeaveRequest $r, string $actualReturn, int $userId, ?string $notes = null): \Modules\Administration\Models\ReturnToWorkRecord
    {
        abort_unless($r->status === 'completed', 422);
        $status = Carbon::parse($actualReturn)->lte($r->end_date->copy()->addDay()) ? 'on_time' : 'late';
        $rtw = $r->returnToWork()->create(['expected_return' => $r->end_date->copy()->addDay(), 'actual_return' => $actualReturn, 'status' => $status, 'notes' => $notes, 'confirmed_by' => $userId]);
        $r->update(['status' => 'returned_to_work']);
        Audit::record('hr.return_to_work', null, null, ['lr' => $r->request_number], $r->branch_id);
        return $rtw;
    }

    /** Payroll-ready summary: approved leave only. */
    public function payrollSummary(int $employeeId, string $from, string $to): array
    {
        $reqs = LeaveRequest::where('employee_id', $employeeId)->whereIn('status', ['approved', 'in_progress', 'completed', 'returned_to_work'])
            ->where(fn ($q) => $q->whereBetween('start_date', [$from, $to])->orWhereBetween('end_date', [$from, $to]))
            ->with('leaveType:id,code,paid')->get();
        return ['approved_days' => $reqs->sum('days'), 'paid_days' => $reqs->where('leaveType.paid', true)->sum('days'), 'unpaid_days' => $reqs->where('leaveType.paid', false)->sum('days'), 'requests' => $reqs->count()];
    }
}
