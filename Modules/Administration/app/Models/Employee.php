<?php

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','title','department_id','grade','active','employee_number','user_id','first_name','middle_name','last_name','gender','date_of_birth','category','job_position_id','job_title','primary_branch_id','supervisor_id','employment_type','start_date','probation_end','status','hr_notes','created_by','contract_number','employee_id','type','termination_reason','approved_by','branch_id','end_date','reason','changed_by','from_status','to_status','effective_date'])]
class Employee extends Model
{
    protected $casts = ['active' => 'boolean', 'date_of_birth' => 'date', 'start_date' => 'date', 'end_date' => 'date', 'probation_end' => 'date', 'effective_date' => 'date'];
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function department(): BelongsTo { return $this->belongsTo(\Modules\HospitalOperations\Models\Department::class); }
    public function position(): BelongsTo { return $this->belongsTo(JobPosition::class, 'job_position_id'); }
    public function primaryBranch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class, 'primary_branch_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(Employee::class, 'supervisor_id'); }
    public function contracts(): HasMany { return $this->hasMany(EmploymentContract::class); }
    public function assignments(): HasMany { return $this->hasMany(EmployeeAssignment::class); }
    public function statusHistories(): HasMany { return $this->hasMany(EmployeeStatusHistory::class); }
    public function getFullNameAttribute(): string { return trim("{$this->first_name} {$this->middle_name} {$this->last_name}"); }
    public function attendanceRecords() { return $this->hasMany(AttendanceRecord::class); }
    public function salaries() { return $this->hasMany(EmployeeSalary::class); }
    public function objectives() { return $this->hasMany(EmployeeObjective::class); }
}
