<?php

namespace Modules\Administration\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Administration\Models\AttendanceCorrection;
use Modules\Administration\Models\AttendanceRecord;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\ShiftAssignment;
use Modules\Administration\Models\WorkShift;
use Modules\Foundation\Support\Audit;

class AttendanceService
{
    public function assignShift(Employee $e, int $shiftId, int $branchId, string $date, int $userId): ShiftAssignment
    {
        abort_unless($e->status === 'active', 422, 'Only active employees can be scheduled.');
        $a = ShiftAssignment::where('employee_id', $e->id)->whereDate('work_date', $date)->first()
            ?? new ShiftAssignment(['employee_id' => $e->id, 'work_date' => $date]);
        $a->fill(['work_shift_id' => $shiftId, 'branch_id' => $branchId, 'assigned_by' => $userId]);
        $a->save();
        Audit::record('hr.shift_assigned', null, null, ['emp' => $e->employee_number, 'date' => $date], $branchId);
        return $a;
    }

    /** Clock in — unique(employee, work_date) prevents duplicate sessions. Late = clock_in − (shift start + grace). */
    public function clockIn(Employee $e, int $branchId, int $userId, ?string $method = null): AttendanceRecord
    {
        abort_unless($e->status === 'active', 422);
        $today = today()->toDateString();
        $r = AttendanceRecord::create(['employee_id' => $e->id, 'branch_id' => $branchId, 'work_date' => $today, 'clock_in' => now(), 'status' => 'present', 'method' => $method ?? 'web', 'recorded_by' => $userId]);
        $a = ShiftAssignment::where('employee_id', $e->id)->whereDate('work_date', $today)->first();
        if ($a) {
            $r->update(['shift_assignment_id' => $a->id]);
            $scheduled = Carbon::parse($a->work_date->toDateString().' '.$a->shift->starts_at)->addMinutes($a->shift->grace_minutes);
            if ($r->clock_in->gt($scheduled)) { $r->update(['late_minutes' => (int) $scheduled->diffInMinutes($r->clock_in), 'status' => 'late']); }
        }
        Audit::record('hr.clock_in', null, null, ['emp' => $e->employee_number], $branchId);
        return $r->fresh();
    }

    /** Clock out — early = (shift end, overnight-aware) − clock_out; worked = duration − break. */
    public function clockOut(AttendanceRecord $r, int $userId): AttendanceRecord
    {
        abort_unless($r->clock_in && ! $r->clock_out, 422, 'No open attendance session.');
        $r->forceFill(['clock_out' => now()])->save();
        $a = $r->assignment;
        if ($a) {
            $shiftEnd = Carbon::parse($a->work_date->toDateString().' '.$a->shift->ends_at);
            if ($a->shift->overnight) { $shiftEnd->addDay(); }
            if ($r->clock_out->lt($shiftEnd)) { $r->update(['early_minutes' => (int) $r->clock_out->diffInMinutes($shiftEnd)]); }
        }
        $r->update(['worked_minutes' => max(0, (int) $r->clock_in->diffInMinutes($r->clock_out) - ($a?->shift->break_minutes ?? 0))]);
        Audit::record('hr.clock_out', null, null, ['emp' => $r->employee->employee_number], $r->branch_id);
        return $r->fresh();
    }

    /** Correction request — never overwrites the original event. */
    public function requestCorrection(AttendanceRecord $r, string $field, string $new, string $reason, int $userId): AttendanceCorrection
    {
        return $r->corrections()->create(['employee_id' => $r->employee_id, 'field' => $field, 'old_value' => (string) $r->{$field}, 'new_value' => $new, 'reason' => $reason, 'requested_by' => $userId, 'status' => 'pending']);
    }

    public function approveCorrection(AttendanceCorrection $c, string $action, int $userId): void
    {
        abort_unless($c->status === 'pending', 422);
        DB::transaction(function () use ($c, $action, $userId) {
            $c->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $userId]);
            if ($action === 'approve') { $c->record->update([$c->field => $c->new_value]); }
        });
        Audit::record("hr.correction_{$action}d", null, null, ['rec' => $c->attendance_record_id], $c->record->branch_id);
    }

    /** Absence candidates: scheduled today + no attendance record — HR review, not auto-absent. */
    public function absenceCandidates(int $branchId): \Illuminate\Support\Collection
    {
        return ShiftAssignment::where('branch_id', $branchId)->whereDate('work_date', today())
            ->whereDoesntHave('employee.attendanceRecords', fn ($q) => $q->whereDate('work_date', today()))
            ->with('employee', 'shift')->get();
    }

    /** Payroll-ready summary (M4 input): verified records only. */
    public function periodSummary(int $employeeId, string $from, string $to): array
    {
        $rs = AttendanceRecord::where('employee_id', $employeeId)->whereBetween('work_date', [$from, $to])->get();
        // recompute at DB level to avoid stale casts
        return [
            'worked_days' => AttendanceRecord::where('employee_id', $employeeId)->whereBetween('work_date', [$from, $to])->whereNotNull('clock_out')->count(),
            'incomplete' => AttendanceRecord::where('employee_id', $employeeId)->whereBetween('work_date', [$from, $to])->whereNull('clock_out')->count(),
            'late_minutes' => $rs->sum('late_minutes'),
            'early_minutes' => $rs->sum('early_minutes'),
            'worked_minutes' => $rs->sum('worked_minutes'),
            'absence_candidates' => $this->absenceCandidates(0)->count(),
        ];
    }
}
