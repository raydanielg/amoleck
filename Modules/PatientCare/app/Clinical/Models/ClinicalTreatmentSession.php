<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['session_number', 'patient_id', 'branch_id', 'clinical_encounter_id', 'clinical_treatment_plan_id', 'physiotherapist_id', 'treating_physiotherapist_id', 'type', 'status', 'session_at', 'pre_condition', 'pre_pain', 'pre_functional', 'pre_changes', 'pre_medical_updates', 'pre_not_assessed', 'pre_not_assessed_reason', 'post_pain', 'post_functional', 'post_feedback', 'overall_tolerance', 'stop_reason', 'post_next_steps', 'created_by'])]
class ClinicalTreatmentSession extends Model
{
    public const TYPES = ['initial', 'routine', 'follow_up', 'reassessment', 'post_op', 'discharge'];
    public const STATUSES = ['draft', 'in_progress', 'pending_review', 'finalized', 'amended', 'cancelled'];
    public const TOLERANCES = ['well_tolerated', 'tolerated_difficulty', 'limited', 'poor', 'unable', 'stopped_clinical'];

    protected $casts = ['session_at' => 'datetime', 'pre_not_assessed' => 'boolean'];

    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function plan(): BelongsTo { return $this->belongsTo(ClinicalTreatmentPlan::class, 'clinical_treatment_plan_id'); }
    public function physiotherapist(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'physiotherapist_id'); }
    public function treatingPhysiotherapist(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'treating_physiotherapist_id'); }
    public function interventions(): HasMany { return $this->hasMany(ClinicalSessionIntervention::class); }
    public function notes(): HasOne { return $this->hasOne(ClinicalSessionNote::class); }
    public function adverseEvents(): HasMany { return $this->hasMany(ClinicalSessionAdverseEvent::class); }
    public function reviews(): HasMany { return $this->hasMany(ClinicalSessionReview::class); }
    public function versions(): HasMany { return $this->hasMany(ClinicalSessionVersion::class); }
}
