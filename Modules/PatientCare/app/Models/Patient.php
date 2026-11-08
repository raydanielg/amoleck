<?php

namespace Modules\PatientCare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Foundation\Models\Branch;

#[Fillable(['mrn', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'nationality', 'marital_status', 'photo', 'phone', 'alt_phone', 'email', 'region', 'district', 'ward', 'address', 'registration_branch_id', 'primary_branch_id', 'registration_source', 'referred_by', 'registered_by', 'status', 'allergy_status', 'admin_notes'])]
class Patient extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'archived', 'deceased'];
    public const GENDERS = ['male', 'female', 'other'];
    public const ID_TYPES = ['national_id', 'passport', 'voter_id', 'driving_licence', 'other'];

    protected $casts = ['date_of_birth' => 'date'];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} ".($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function registrationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'registration_branch_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function identifications(): HasMany
    {
        return $this->hasMany(PatientIdentification::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(PatientEmergencyContact::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(PatientGuardian::class);
    }

    public function branchHistory(): HasMany
    {
        return $this->hasMany(PatientBranch::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'patient_branches')->withPivot('event')->withTimestamps();
    }

    public function medicalEntries(): HasMany
    {
        return $this->hasMany(PatientMedicalEntry::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PatientStatusHistory::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PatientMedicalCondition::class);
    }

    public function surgicalHistories(): HasMany
    {
        return $this->hasMany(PatientSurgicalHistory::class);
    }

    public function medicationHistories(): HasMany
    {
        return $this->hasMany(PatientMedicationHistory::class);
    }

    public function familyHistories(): HasMany
    {
        return $this->hasMany(PatientFamilyHistory::class);
    }

    public function socialHistories(): HasMany
    {
        return $this->hasMany(PatientSocialHistory::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(PatientReferral::class);
    }

    public function branchTransfers(): HasMany
    {
        return $this->hasMany(PatientBranchTransfer::class);
    }

    public function recordAmendments(): HasMany
    {
        return $this->hasMany(PatientRecordAmendment::class);
    }

    public function clinicalEncounters(): HasMany
    {
        return $this->hasMany(\Modules\PatientCare\Clinical\Models\ClinicalEncounter::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(\Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan::class);
    }

    public function clinicalSessions(): HasMany
    {
        return $this->hasMany(\Modules\PatientCare\Clinical\Models\ClinicalTreatmentSession::class);
    }

    public function outcomeRecords(): HasMany
    {
        return $this->hasMany(\Modules\PatientCare\Clinical\Models\ClinicalOutcomeRecord::class);
    }

    public function homeExercisePrograms(): HasMany
    {
        return $this->hasMany(\Modules\PatientCare\Clinical\Models\ClinicalHomeExerciseProgram::class);
    }
}
