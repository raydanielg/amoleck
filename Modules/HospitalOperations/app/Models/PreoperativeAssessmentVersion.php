<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['preoperative_assessment_id','version','snapshot','action','acted_by'])]
class PreoperativeAssessmentVersion extends Model
{
    protected $casts = ['snapshot' => 'array'];
}
