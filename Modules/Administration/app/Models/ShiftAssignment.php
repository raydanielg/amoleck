<?php

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code','name','starts_at','ends_at','grace_minutes','break_minutes','overnight','active','employee_id','work_shift_id','branch_id','work_date','assigned_by','shift_assignment_id','clock_in','clock_out','late_minutes','early_minutes','worked_minutes','status','method','recorded_by','attendance_record_id','field','old_value','new_value','reason','requested_by','reviewed_by'])]
class ShiftAssignment extends Model
{
    protected $casts = ['overnight' => 'boolean', 'active' => 'boolean', 'work_date' => 'date', 'clock_in' => 'datetime', 'clock_out' => 'datetime', 'starts_at' => 'string', 'ends_at' => 'string'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function shift(): BelongsTo { return $this->belongsTo(WorkShift::class, 'work_shift_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id'); }
    public function record(): BelongsTo { return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id'); }
}
