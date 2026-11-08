<?php

namespace Modules\Administration\Services;

use Illuminate\Support\Facades\DB;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\EmployeeAssignment;
use Modules\Administration\Models\EmploymentContract;
use Modules\Foundation\Support\Audit;

class HrService
{
    public const TRANSITIONS = [
        'pending' => ['active', 'archived'],
        'active' => ['on_probation', 'on_leave', 'suspended', 'resigned', 'terminated', 'retired', 'archived'],
        'on_probation' => ['active', 'terminated'],
        'on_leave' => ['active'],
        'suspended' => ['active', 'terminated'],
        'resigned' => ['archived'],
        'terminated' => ['archived'],
        'retired' => ['archived'],
        'archived' => [],
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

    public function register(array $data, int $userId): Employee
    {
        return DB::transaction(function () use ($data, $userId) {
            $e = Employee::create($data + ['employee_number' => $this->nextNumber('EMP', 'employees', 'employee_number'), 'status' => 'pending', 'created_by' => $userId]);
            $e->statusHistories()->create(['to_status' => 'pending', 'changed_by' => $userId]);
            if ($data['primary_branch_id'] ?? null) {
                $e->assignments()->create(['branch_id' => $data['primary_branch_id'], 'department_id' => $data['department_id'] ?? null, 'job_position_id' => $data['job_position_id'] ?? null, 'start_date' => $data['start_date'] ?? today(), 'status' => 'active', 'changed_by' => $userId]);
            }
            Audit::record('hr.employee_registered', null, null, ['emp' => $e->employee_number], $e->primary_branch_id);
            return $e;
        });
    }

    public function transition(Employee $e, string $to, int $userId, ?string $reason = null): Employee
    {
        abort_unless(in_array($to, static::TRANSITIONS[$e->status] ?? []), 422, "Cannot move {$e->status}→{$to}.");
        abort_if(in_array($to, ['terminated', 'suspended']) && ! $reason, 422, 'Reason required.');
        DB::transaction(function () use ($e, $to, $userId, $reason) {
            $e->statusHistories()->create(['from_status' => $e->status, 'to_status' => $to, 'reason' => $reason, 'effective_date' => today(), 'changed_by' => $userId]);
            $e->update(['status' => $to]);
        });
        Audit::record("hr.employee_{$to}", null, ['status' => $e->status], ['status' => $to], $e->primary_branch_id);
        return $e->fresh();
    }

    public function transfer(Employee $e, int $branchId, ?int $deptId, int $userId, ?string $reason = null): EmployeeAssignment
    {
        abort_unless($e->status === 'active', 422, 'Only active employees can be transferred.');
        return DB::transaction(function () use ($e, $branchId, $deptId, $userId, $reason) {
            $e->assignments()->where('status', 'active')->update(['status' => 'ended', 'end_date' => today()]);
            $a = $e->assignments()->create(['branch_id' => $branchId, 'department_id' => $deptId ?? $e->department_id, 'job_position_id' => $e->job_position_id, 'start_date' => today(), 'status' => 'active', 'reason' => $reason, 'changed_by' => $userId]);
            $e->update(['primary_branch_id' => $branchId]);
            Audit::record('hr.employee_transferred', null, null, ['emp' => $e->employee_number, 'branch' => $branchId], $branchId);
            return $a;
        });
    }

    public function createContract(Employee $e, array $data, int $userId): EmploymentContract
    {
        $c = $e->contracts()->create($data + ['contract_number' => $this->nextNumber('CT', 'employment_contracts', 'contract_number'), 'status' => 'draft', 'created_by' => $userId]);
        Audit::record('hr.contract_created', null, null, ['ct' => $c->contract_number], $e->primary_branch_id);
        return $c;
    }

    public function renewContract(EmploymentContract $old, array $data, int $userId): EmploymentContract
    {
        abort_unless(in_array($old->status, ['active', 'expired']), 422);
        return DB::transaction(function () use ($old, $data, $userId) {
            $old->update(['status' => 'renewed']);
            $new = $old->employee->contracts()->create($data + ['contract_number' => $this->nextNumber('CT', 'employment_contracts', 'contract_number'), 'status' => 'active', 'created_by' => $userId]);
            Audit::record('hr.contract_renewed', null, ['ct' => $old->contract_number], ['ct' => $new->contract_number]);
            return $new;
        });
    }

    public function dashboard(int $branchId): array
    {
        $q = Employee::where('primary_branch_id', $branchId);
        return [
            'total' => (clone $q)->count(),
            'active' => (clone $q)->where('status', 'active')->count(),
            'on_probation' => (clone $q)->where('status', 'on_probation')->count(),
            'on_leave' => (clone $q)->where('status', 'on_leave')->count(),
            'suspended' => (clone $q)->where('status', 'suspended')->count(),
            'expiring_contracts' => EmploymentContract::whereHas('employee', fn ($x) => $x->where('primary_branch_id', $branchId))->where('status', 'active')->whereNotNull('end_date')->where('end_date', '<=', now()->addDays(60))->count(),
        ];
    }
}
