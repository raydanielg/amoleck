<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code','name','stage','version','items','active','approved_by','effective_from'])]
class SurgicalChecklistTemplate extends Model
{
    protected $casts = ['items' => 'array', 'active' => 'boolean', 'effective_from' => 'date'];
}
