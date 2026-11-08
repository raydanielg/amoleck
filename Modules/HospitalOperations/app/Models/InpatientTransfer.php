<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','branch_id','department_id','category','manager_id','capacity','active','ward_id','number','status','cleaning_since','admission_number','patient_id','clinician_id','type','source','priority','reason','room_id','bed_id','admitted_at','discharged_at','discharge_type','discharge_reason','discharge_approved_by','created_by','admission_id','allocated_at','released_at','allocated_by','from_status','to_status','changed_by','from_bed_id','to_bed_id','from_ward_id','to_ward_id','requested_by','completed_at'])]
class InpatientTransfer extends Model
{
    protected $casts = ['active' => 'boolean', 'cleaning_since' => 'datetime', 'admitted_at' => 'datetime', 'discharged_at' => 'datetime', 'allocated_at' => 'datetime', 'released_at' => 'datetime', 'completed_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function ward(): BelongsTo { return $this->belongsTo(Ward::class); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function bed(): BelongsTo { return $this->belongsTo(Bed::class); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function clinician(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'clinician_id'); }
    public function rooms(): HasMany { return $this->hasMany(Room::class); }
    public function beds(): HasMany { return $this->hasMany(Bed::class); }
    public function allocations(): HasMany { return $this->hasMany(BedAllocation::class); }
    public function statusHistories(): HasMany { return $this->hasMany(AdmissionStatusHistory::class); }
    public function transfers(): HasMany { return $this->hasMany(InpatientTransfer::class); }
}
