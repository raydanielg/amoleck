<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['record_number','surgical_case_id','surgical_operation_id','patient_id','branch_id','recovery_bed_id','arrived_at','received_by','anesthesia_handover','clinical_notes','status','cleared_by','cleared_at','transferred_at','disposition','created_by'])]
class SurgicalRecoveryRecord extends Model
{
    public const STATUSES = ['awaiting_recovery','received','under_observation','reassessment_required','ready_for_review','cleared_for_transfer','transferred','escalated','cancelled'];
    protected $casts = ['arrived_at' => 'datetime', 'cleared_at' => 'datetime', 'transferred_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function bed(): BelongsTo { return $this->belongsTo(RecoveryBed::class,'recovery_bed_id'); }
    public function assessments(): HasMany { return $this->hasMany(SurgicalRecoveryAssessment::class); }
    public function bedAssignments(): HasMany { return $this->hasMany(RecoveryBedAssignment::class); }
}
