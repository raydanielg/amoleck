<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinical_exercise_id', 'clinical_exercise_prescription_id', 'clinical_home_exercise_program_id', 'clinical_prescribed_exercise_id', 'step_number', 'instruction', 'path', 'original_name', 'mime', 'size', 'uploaded_by', 'custom_name', 'sets', 'reps', 'hold_duration', 'rest_duration', 'resistance', 'frequency', 'days_per_week', 'program_duration', 'patient_instructions', 'precautions', 'performed_on', 'status', 'completed_sets', 'completed_reps', 'actual_duration', 'difficulty', 'pain', 'non_completion_reason', 'comments', 'source', 'recorded_by', 'entity_type', 'entity_id', 'version', 'snapshot', 'action', 'acted_by', 'reason'])]
class ClinicalExerciseVersion extends Model
{
    protected $casts = ['performed_on' => 'date', 'snapshot' => 'array'];
    public function exercise(): BelongsTo { return $this->belongsTo(ClinicalExercise::class, 'clinical_exercise_id'); }
    public function program(): BelongsTo { return $this->belongsTo(ClinicalHomeExerciseProgram::class, 'clinical_home_exercise_program_id'); }
}
