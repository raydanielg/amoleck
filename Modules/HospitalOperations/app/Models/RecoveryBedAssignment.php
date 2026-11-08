<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_recovery_record_id','recovery_bed_id','assigned_at','released_at','assigned_by'])]
class RecoveryBedAssignment extends Model
{
    protected $casts = ['assigned_at' => 'datetime', 'released_at' => 'datetime'];
}
