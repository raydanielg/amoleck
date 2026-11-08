<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'category', 'description', 'clinical_purpose', 'instructions_en', 'instructions_sw', 'applicable_regions', 'difficulty', 'equipment', 'contraindications', 'precautions', 'active'])]
class ClinicalExercise extends Model
{
    public const CATEGORIES = ['range_of_motion', 'strengthening', 'stretching', 'balance_coordination', 'gait_mobility', 'postural', 'neurological', 'cardiopulmonary', 'post_op', 'custom'];
    protected $casts = ['active' => 'boolean'];
    public function media(): HasMany { return $this->hasMany(ClinicalExerciseMedia::class); }
}
