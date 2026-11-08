<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clinical_service_id','service_name','department_id','category','price','currency','branch_id','effective_from','effective_to','tax_rate','status','reject_reason','created_by','approved_by','approved_at','charge_number','patient_id','source','source_id','service_price_id','service_date','quantity','unit_price','discount','tax','total','description','reviewed_by','posted_at','patient_charge_id','from_status','to_status','reason','changed_by','type','amount','requested_by'])]
class ChargeStatusHistory extends Model
{
    protected $casts = ['effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime', 'service_date' => 'datetime', 'posted_at' => 'datetime', 'price' => 'decimal:2', 'unit_price' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'amount' => 'decimal:2', 'tax_rate' => 'decimal:2'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function service(): BelongsTo { return $this->belongsTo(\Modules\HospitalOperations\Models\ClinicalService::class, 'clinical_service_id'); }
    public function statusHistories(): HasMany { return $this->hasMany(ChargeStatusHistory::class, 'patient_charge_id'); }
    public function adjustments(): HasMany { return $this->hasMany(ChargeAdjustment::class, 'patient_charge_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
