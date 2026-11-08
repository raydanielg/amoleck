<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_case_id','surgical_booking_id','category','reason','patient_notified','notes','cancelled_by','approved_by'])]
class SurgicalCancellation extends Model
{
    protected $casts = ['patient_notified' => 'boolean'];
}
