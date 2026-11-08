<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['plan_number', 'patient_id', 'branch_id', 'clinical_encounter_id', 'physiotherapist_id', 'approved_by', 'approved_at', 'status', 'clinical_indication', 'expected_outcomes', 'precautions', 'sessions_per_week', 'expected_sessions', 'expected_period', 'review_at', 'created_by'])]
class ClinicalTreatmentPlan extends Model
{
    public const STATUSES = ['draft', 'pending_review', 'approved', 'active', 'on_hold', 'completed', 'discontinued', 'cancelled'];

    protected $casts = ['review_at' => 'date', 'approved_at' => 'datetime'];

    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function encounter(): BelongsTo { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
    public function physiotherapist(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'physiotherapist_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }
    public function problems(): HasMany { return $this->hasMany(ClinicalTreatmentPlanProblem::class); }
    public function goals(): HasMany { return $this->hasMany(ClinicalTreatmentGoal::class); }
    public function plannedInterventions(): HasMany { return $this->hasMany(ClinicalPlannedIntervention::class); }
    public function reviews(): HasMany { return $this->hasMany(ClinicalTreatmentPlanReview::class); }
    public function versions(): HasMany { return $this->hasMany(ClinicalTreatmentPlanVersion::class); }
    public function precautionRecords(): HasMany { return $this->hasMany(ClinicalTreatmentPrecaution::class); }
}
