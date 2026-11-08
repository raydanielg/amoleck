<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_consultation_id','version','snapshot','action','acted_by'])]
class SurgicalConsultationVersion extends Model
{
    protected $casts = ['snapshot' => 'array'];
}
