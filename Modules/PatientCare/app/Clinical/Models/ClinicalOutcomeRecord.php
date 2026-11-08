<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','category','description','clinical_purpose','measurement_method','supported_units','scoring_format','interpretation','applicable_population','instructions','active','version_no','clinical_outcome_measure_id','patient_id','clinical_encounter_id','clinical_treatment_plan_id','clinical_treatment_session_id','branch_id','value_numeric','value_categorical','unit','status','not_assessed_reason','assessed_at','clinician_id','observations','reassessment_number','clinical_reassessment_id','clinical_treatment_goal_id','baseline','current_value','target','evidence','comments','physiotherapist_id','subjective','objective','interpretation','recommendation','recommendation_reason','discharge_number','discharge_on','type','initial_status','final_status','baseline_pain','final_pain','functional_status','goal_achievement','outstanding_concerns','hep_recommendation','follow_up','precautions','approved_by','version','snapshot','action','acted_by','reason','created_by'])]
class ClinicalOutcomeRecord extends Model
{
    protected $casts = ['supported_units' => 'array', 'assessed_at' => 'datetime', 'discharge_on' => 'date', 'snapshot' => 'array', 'active' => 'boolean'];

    public function measure(): BelongsTo { return $this->belongsTo(ClinicalOutcomeMeasure::class, 'clinical_outcome_measure_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function plan(): BelongsTo { return $this->belongsTo(ClinicalTreatmentPlan::class, 'clinical_treatment_plan_id'); }
    public function physiotherapist(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'physiotherapist_id'); }
    public function clinician(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'clinician_id'); }
    public function goalReviews(): HasMany { return $this->hasMany(ClinicalReassessmentGoalReview::class, 'clinical_reassessment_id'); }
    public function versions(): HasMany { return $this->hasMany(ClinicalDischargeVersion::class, 'clinical_discharge_id'); }
    public function reVersions(): HasMany { return $this->hasMany(ClinicalReassessmentVersion::class, 'clinical_reassessment_id'); }
    public function goal(): BelongsTo { return $this->belongsTo(ClinicalTreatmentGoal::class, 'clinical_treatment_goal_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }
}
