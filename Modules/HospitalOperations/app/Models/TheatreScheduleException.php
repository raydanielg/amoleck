<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['operating_theatre_id','date','available','reason','created_by'])]
class TheatreScheduleException extends Model
{
    protected $casts = ['date' => 'date', 'available' => 'boolean'];
}
