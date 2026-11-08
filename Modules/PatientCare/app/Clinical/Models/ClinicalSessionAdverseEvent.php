<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinical_treatment_session_id', 'clinical_planned_intervention_id', 'clinical_intervention_id', 'clinical_treatment_goal_id', 'name', 'status', 'not_performed_reason', 'technique', 'dosage', 'intensity', 'duration', 'sets', 'reps', 'resistance', 'equipment', 'tolerance', 'observations', 'modification', 'modification_reason', 'template', 'subjective', 'objective', 'assessment', 'plan', 'narrative', 'description', 'action_taken', 'escalation', 'recorded_by', 'reviewer_id', 'decision', 'reason', 'version', 'snapshot', 'action', 'acted_by'])]
class ClinicalSessionAdverseEvent extends Model
{
    protected $casts = ['snapshot' => 'array'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClinicalTreatmentSession::class, 'clinical_treatment_session_id');
    }
}
