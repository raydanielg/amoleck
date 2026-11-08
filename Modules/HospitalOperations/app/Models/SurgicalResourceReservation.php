<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_booking_id','reservable_type','reservable_id','starts_at','ends_at','status','created_by'])]
class SurgicalResourceReservation extends Model
{
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
