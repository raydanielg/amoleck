<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_case_id','patient_id','branch_id','anesthetist_id','medical_history','previous_anesthesia','allergies','current_medications','examination','investigations','airway_assessment','considerations','proposed_technique','clinical_notes','status','approved_by','approved_at','version'])]
class AnesthesiaPreassessment extends Model
{
    protected $casts = ['approved_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function anesthetist(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'anesthetist_id'); }
    public function versions(): HasMany { return $this->hasMany(AnesthesiaPreassessmentVersion::class); }
}
