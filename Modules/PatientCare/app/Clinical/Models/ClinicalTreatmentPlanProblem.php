<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinical_treatment_plan_id', 'clinical_problem_id', 'clinical_intervention_id', 'clinical_treatment_plan_problem_id', 'clinical_treatment_goal_id', 'name', 'category', 'description', 'clinical_purpose', 'applicable_regions', 'contraindications', 'precautions', 'equipment', 'active', 'body_region', 'laterality', 'supporting_findings', 'functional_impact', 'baseline', 'priority', 'target_outcome', 'status', 'type', 'target', 'unit', 'target_date', 'measurement_method', 'clinician_id', 'value', 'comment', 'recorded_by', 'frequency', 'duration', 'intensity', 'sets', 'reps', 'hold_duration', 'rest_period', 'resistance', 'expected_response', 'physiotherapist_id', 'reviewer_id', 'decision', 'comments', 'rejection_reason', 'version', 'snapshot', 'action', 'acted_by', 'reason'])]
class ClinicalTreatmentPlanProblem extends Model
{
    protected $casts = ['active' => 'boolean', 'target_date' => 'date', 'snapshot' => 'array'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ClinicalTreatmentPlan::class, 'clinical_treatment_plan_id');
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(ClinicalGoalProgress::class, 'clinical_treatment_goal_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'acted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewer_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(ClinicalIntervention::class, 'clinical_intervention_id');
    }
}
