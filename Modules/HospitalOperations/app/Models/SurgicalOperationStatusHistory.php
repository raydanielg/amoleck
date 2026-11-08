<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_operation_id','from_status','to_status','reason','changed_by'])]
class SurgicalOperationStatusHistory extends Model
{
    protected $table = 'surgical_operation_status_histories';
}
