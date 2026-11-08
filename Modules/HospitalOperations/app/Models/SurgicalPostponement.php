<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_case_id','surgical_booking_id','category','reason','rescheduled_to','patient_notified','notes','postponed_by','approved_by'])]
class SurgicalPostponement extends Model
{
    protected $casts = ['rescheduled_to' => 'date', 'patient_notified' => 'boolean'];
}
