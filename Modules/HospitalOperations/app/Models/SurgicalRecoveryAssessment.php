<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_recovery_record_id','assessed_by','assessed_at','observations','pain_score','consciousness','interventions','adverse_events','notes','outcome','disposition_recommendation'])]
class SurgicalRecoveryAssessment extends Model
{
    protected $casts = ['assessed_at' => 'datetime'];
}
