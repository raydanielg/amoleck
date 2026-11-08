<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['operating_theatre_id','from_status','to_status','reason','changed_by'])]
class OperatingTheatreStatusHistory extends Model
{
    protected $table = 'operating_theatre_status_histories';
}
