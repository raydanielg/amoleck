<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_booking_id','action','before','after','reason','changed_by'])]
class SurgicalBookingHistory extends Model
{
    protected $table = 'surgical_booking_histories';
    protected $casts = ['before' => 'array', 'after' => 'array'];
}
