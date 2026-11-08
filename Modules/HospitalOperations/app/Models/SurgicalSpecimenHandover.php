<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_specimen_id','from_user_id','to_user_id','received_by_name','dispatched_at','received_at','notes'])]
class SurgicalSpecimenHandover extends Model
{
    protected $casts = ['dispatched_at' => 'datetime', 'received_at' => 'datetime'];
}
