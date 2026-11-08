<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['case_number','patient_id','branch_id','department_id','surgical_specialty_id','surgical_procedure_id','procedure_version','ordering_clinician_id','surgeon_id','referring_clinician_id','clinical_encounter_id','indication','diagnosis','priority','proposed_date','status','notes','approved_by','approved_at','created_by'])]
class SurgicalCase extends Model
{
    public const STATUSES = ['draft','submitted','pending_clinical_review','pending_surgical_approval','approved','rejected','awaiting_preoperative_assessment','awaiting_consent','ready_for_scheduling','scheduled','preoperative_preparation','ready_for_theatre','in_theatre','procedure_completed','recovery','postoperative_care','completed','cancelled','postponed'];
    public const PRIORITIES = ['emergency','urgent','semi_urgent','elective'];
    protected $casts = ['proposed_date' => 'date', 'approved_at' => 'datetime'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function specialty(): BelongsTo { return $this->belongsTo(SurgicalSpecialty::class,'surgical_specialty_id'); }
    public function procedure(): BelongsTo { return $this->belongsTo(SurgicalProcedure::class,'surgical_procedure_id'); }
    public function orderingClinician(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'ordering_clinician_id'); }
    public function surgeon(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'surgeon_id'); }
    public function encounter(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Clinical\Models\ClinicalEncounter::class,'clinical_encounter_id'); }
    public function statusHistories(): HasMany { return $this->hasMany(SurgicalCaseStatusHistory::class); }
    public function consultation(): HasOne { return $this->hasOne(SurgicalConsultation::class); }
    public function diagnoses(): HasMany { return $this->hasMany(SurgicalCaseDiagnosis::class); }
    public function preopAssessment(): HasOne { return $this->hasOne(PreoperativeAssessment::class); }
    public function anesthesiaPreassessment(): HasOne { return $this->hasOne(AnesthesiaPreassessment::class); }
    public function consents(): HasMany { return $this->hasMany(SurgicalConsent::class); }
    public function checklistItems(): HasMany { return $this->hasMany(SurgicalChecklistItem::class); }
    public function bookings(): HasMany { return $this->hasMany(SurgicalBooking::class); }
    public function teamAssignments(): HasMany { return $this->hasMany(SurgicalTeamAssignment::class); }
    public function operations(): HasMany { return $this->hasMany(SurgicalOperation::class); }
    public function safetyVerifications(): HasMany { return $this->hasMany(SurgicalSafetyVerification::class); }
    public function safetyExceptions(): HasMany { return $this->hasMany(SurgicalSafetyException::class); }
    public function bloodRequirements(): HasMany { return $this->hasMany(SurgicalBloodRequirement::class); }
    public function recoveryRecord(): HasOne { return $this->hasOne(SurgicalRecoveryRecord::class); }
    public function handover(): HasOne { return $this->hasOne(SurgicalHandover::class); }
    public function documents(): HasMany { return $this->hasMany(SurgicalCaseDocument::class); }
}
