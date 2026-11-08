<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'department', 'default_duration', 'description', 'clinician_category', 'active', 'user_id', 'branch_id', 'day_of_week', 'start_time', 'end_time', 'break_start', 'break_end', 'slot_duration', 'effective_from', 'effective_to', 'type', 'capacity', 'appointment_id', 'from_status', 'to_status', 'reason', 'changed_by', 'previous_date', 'previous_start', 'previous_end', 'new_date', 'new_start', 'new_end'])]
class AppointmentStatusHistory extends Model
{
    protected $casts = ['active' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date', 'previous_date' => 'date', 'new_date' => 'date'];
}
