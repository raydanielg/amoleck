<?php

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','paid','requires_document','max_days','active','date','branch_id','request_number','employee_id','leave_type_id','start_date','end_date','days','status','reason','reject_reason','created_by','reviewed_by','reviewed_at','type','reference','leave_request_id','expected_return','actual_return','notes','confirmed_by'])]
class ReturnToWorkRecord extends Model
{
    protected $casts = ['paid' => 'boolean', 'requires_document' => 'boolean', 'active' => 'boolean', 'date' => 'date', 'start_date' => 'date', 'end_date' => 'date', 'expected_return' => 'date', 'actual_return' => 'date', 'reviewed_at' => 'datetime', 'days' => 'decimal:1'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function leaveRequest(): BelongsTo { return $this->belongsTo(LeaveRequest::class, 'leave_request_id'); }
    public function returnToWork(): HasMany { return $this->hasMany(ReturnToWorkRecord::class, 'leave_request_id'); }
}
