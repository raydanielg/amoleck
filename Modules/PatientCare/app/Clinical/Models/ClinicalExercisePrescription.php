<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['rx_number', 'patient_id', 'clinical_treatment_plan_id', 'clinical_treatment_goal_id', 'branch_id', 'physiotherapist_id', 'approved_by', 'prescribed_on', 'start_on', 'review_on', 'end_on', 'status', 'instructions', 'created_by'])]
class ClinicalExercisePrescription extends Model
{
    public const STATUSES = ['draft', 'pending_review', 'approved', 'active', 'on_hold', 'completed', 'discontinued', 'cancelled'];
    protected $casts = ['prescribed_on' => 'date', 'start_on' => 'date', 'review_on' => 'date', 'end_on' => 'date'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function plan(): BelongsTo { return $this->belongsTo(ClinicalTreatmentPlan::class, 'clinical_treatment_plan_id'); }
    public function goal(): BelongsTo { return $this->belongsTo(ClinicalTreatmentGoal::class, 'clinical_treatment_goal_id'); }
    public function physiotherapist(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'physiotherapist_id'); }
    public function exercises(): HasMany { return $this->hasMany(ClinicalPrescribedExercise::class); }
}
