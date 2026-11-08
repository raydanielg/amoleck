<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_case_id','patient_id','branch_id','medical_history','previous_surgery','current_medications','allergies_summary','anesthesia_history','family_social_history','clinical_condition','examination_findings','functional_status','precautions','investigations_summary','outstanding_investigations','clinical_notes','outstanding_actions','readiness_recommendation','status','clinician_id','reviewed_by','assessed_at','cleared_at','version'])]
class PreoperativeAssessment extends Model
{
    protected $casts = ['assessed_at' => 'datetime', 'cleared_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class, 'surgical_case_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function clinician(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'clinician_id'); }
    public function versions(): HasMany { return $this->hasMany(PreoperativeAssessmentVersion::class); }
}
