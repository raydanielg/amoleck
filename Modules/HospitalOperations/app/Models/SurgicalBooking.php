<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['booking_number','surgical_case_id','patient_id','branch_id','operating_theatre_id','scheduled_date','start_time','end_time','prep_minutes','cleaning_minutes','surgeon_id','assistant_surgeon_id','anesthetist_id','priority','emergency_override','status','notes','created_by'])]
class SurgicalBooking extends Model
{
    protected $casts = ['scheduled_date' => 'date', 'emergency_override' => 'boolean'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function theatre(): BelongsTo { return $this->belongsTo(OperatingTheatre::class,'operating_theatre_id'); }
    public function surgeon(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'surgeon_id'); }
    public function anesthetist(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'anesthetist_id'); }
    public function histories(): HasMany { return $this->hasMany(SurgicalBookingHistory::class); }
    public function reservations(): HasMany { return $this->hasMany(SurgicalResourceReservation::class); }
}
