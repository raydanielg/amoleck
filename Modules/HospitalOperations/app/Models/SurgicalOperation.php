<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['operation_number','surgical_case_id','surgical_booking_id','operating_theatre_id','patient_id','branch_id','surgeon_id','anesthetist_id','status','patient_in_at','anesthesia_start_at','anesthesia_end_at','procedure_start_at','procedure_end_at','patient_out_at','preop_diagnosis','postop_diagnosis','findings','narrative','complications','estimated_blood_loss_ml','unexpected_events','outcome','postoperative_destination','created_by'])]
class SurgicalOperation extends Model
{
    protected $casts = ['patient_in_at' => 'datetime', 'anesthesia_start_at' => 'datetime', 'anesthesia_end_at' => 'datetime', 'procedure_start_at' => 'datetime', 'procedure_end_at' => 'datetime', 'patient_out_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function theatre(): BelongsTo { return $this->belongsTo(OperatingTheatre::class,'operating_theatre_id'); }
    public function booking(): BelongsTo { return $this->belongsTo(SurgicalBooking::class,'surgical_booking_id'); }
    public function surgeon(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'surgeon_id'); }
    public function procedures(): HasMany { return $this->hasMany(SurgicalOperationProcedure::class); }
    public function operativeNote(): HasOne { return $this->hasOne(OperativeNote::class); }
    public function anesthesiaRecord(): HasOne { return $this->hasOne(AnesthesiaRecord::class); }
    public function counts(): HasMany { return $this->hasMany(SurgicalCount::class); }
    public function specimens(): HasMany { return $this->hasMany(SurgicalSpecimen::class); }
    public function implants(): HasMany { return $this->hasMany(SurgicalImplant::class); }
    public function consumables(): HasMany { return $this->hasMany(SurgicalConsumableUsage::class); }
    public function statusHistories(): HasMany { return $this->hasMany(SurgicalOperationStatusHistory::class); }
}
