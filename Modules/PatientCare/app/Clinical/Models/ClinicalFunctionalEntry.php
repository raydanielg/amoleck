<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinical_encounter_id', 'mechanism', 'onset_type', 'progression', 'previous_interventions', 'investigations', 'previous_episodes', 'patient_expectations', 'subjective_summary', 'objective_summary', 'functional_impairments', 'interpretation', 'identified_problems_summary', 'limitations', 'recommendations', 'complaint', 'onset_date', 'duration', 'location', 'patient_description', 'aggravating', 'relieving', 'previous_treatment', 'functional_limitations', 'pain_type', 'frequency', 'nprs_current', 'nprs_best', 'nprs_worst', 'not_assessable', 'not_assessable_reason', 'activity', 'level', 'notes', 'category', 'finding', 'body_region', 'joint', 'movement', 'side', 'active_rom', 'passive_rom', 'unit', 'pain_during', 'observations', 'muscle_group', 'grade', 'grade_scale', 'pain_or_limitations', 'test_name', 'result', 'interpretation', 'description', 'severity', 'action_taken', 'recorded_by', 'laterality', 'functional_impact', 'supporting_findings', 'status', 'view', 'region', 'annotation_type', 'note', 'version', 'snapshot', 'action', 'acted_by', 'reason'])]
class ClinicalFunctionalEntry extends Model
{
    protected $casts = ['onset_date' => 'date', 'not_assessable' => 'boolean', 'pain_during' => 'boolean', 'snapshot' => 'array'];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }
}
