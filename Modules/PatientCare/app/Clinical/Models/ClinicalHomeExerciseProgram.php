<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['hep_number', 'patient_id', 'clinical_treatment_plan_id', 'clinical_exercise_prescription_id', 'branch_id', 'physiotherapist_id', 'approved_by', 'issued_on', 'start_on', 'review_on', 'status', 'general_instructions', 'emergency_instructions', 'created_by'])]
class ClinicalHomeExerciseProgram extends Model
{
    public const STATUSES = ['draft', 'pending_review', 'approved', 'active', 'on_hold', 'completed', 'discontinued', 'cancelled'];
    protected $casts = ['issued_on' => 'date', 'start_on' => 'date', 'review_on' => 'date'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function plan(): BelongsTo { return $this->belongsTo(ClinicalTreatmentPlan::class, 'clinical_treatment_plan_id'); }
    public function physiotherapist(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'physiotherapist_id'); }
    public function exercises(): HasMany { return $this->hasMany(ClinicalPrescribedExercise::class); }
    public function adherence(): HasMany { return $this->hasMany(ClinicalExerciseAdherenceRecord::class); }
}
