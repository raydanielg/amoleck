<?php

namespace Modules\PatientCare\Clinical\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['encounter_number', 'patient_id', 'branch_id', 'type', 'status', 'physiotherapist_id', 'referring_professional', 'encounter_at', 'notes', 'created_by', 'updated_by'])]
class ClinicalEncounter extends Model
{
    public const TYPES = ['initial_assessment', 'follow_up', 'reassessment', 'review'];
    public const STATUSES = ['draft', 'in_progress', 'pending_review', 'finalized', 'amended', 'cancelled'];

    protected $casts = ['encounter_at' => 'datetime'];

    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function physiotherapist(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'physiotherapist_id'); }
    public function assessment(): HasOne { return $this->hasOne(ClinicalAssessment::class); }
    public function complaints(): HasMany { return $this->hasMany(ClinicalComplaint::class); }
    public function painAssessments(): HasMany { return $this->hasMany(ClinicalPainAssessment::class); }
    public function functionalEntries(): HasMany { return $this->hasMany(ClinicalFunctionalEntry::class); }
    public function objectiveFindings(): HasMany { return $this->hasMany(ClinicalObjectiveFinding::class); }
    public function romMeasurements(): HasMany { return $this->hasMany(ClinicalRomMeasurement::class); }
    public function strengthMeasurements(): HasMany { return $this->hasMany(ClinicalStrengthMeasurement::class); }
    public function specialTests(): HasMany { return $this->hasMany(ClinicalSpecialTest::class); }
    public function redFlags(): HasMany { return $this->hasMany(ClinicalRedFlag::class); }
    public function problems(): HasMany { return $this->hasMany(ClinicalProblem::class); }
    public function bodyMapAnnotations(): HasMany { return $this->hasMany(ClinicalBodyMapAnnotation::class); }
    public function versions(): HasMany { return $this->hasMany(ClinicalEncounterVersion::class); }
    public function isFinalized(): bool { return in_array($this->status, ['finalized']); }
}
