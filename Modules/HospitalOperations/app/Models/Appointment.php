<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['appointment_number', 'patient_id', 'branch_id', 'appointment_type_id', 'clinician_id', 'hospital_resource_id', 'appointment_date', 'start_time', 'end_time', 'duration', 'priority', 'status', 'reason', 'notes', 'checked_in_at', 'created_by'])]
class Appointment extends Model
{
    public const STATUSES = ['draft', 'pending_confirmation', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'rescheduled', 'no_show'];
    protected $casts = ['appointment_date' => 'date', 'checked_in_at' => 'datetime'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function type(): BelongsTo { return $this->belongsTo(AppointmentType::class, 'appointment_type_id'); }
    public function clinician(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'clinician_id'); }
    public function resource(): BelongsTo { return $this->belongsTo(HospitalResource::class, 'hospital_resource_id'); }
    public function statusHistories(): HasMany { return $this->hasMany(AppointmentStatusHistory::class); }
    public function rescheduleHistories(): HasMany { return $this->hasMany(AppointmentRescheduleHistory::class); }
}
