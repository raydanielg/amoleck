<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['anesthesia_preassessment_id','version','snapshot','action','acted_by'])]
class AnesthesiaPreassessmentVersion extends Model
{
    protected $casts = ['snapshot' => 'array'];
}
