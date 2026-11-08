<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','department','branch_id','active','visit_number','patient_id','appointment_id','source','status','reason','arrived_at','checked_in_at','service_started_at','completed_at','outcome','notes','created_by','ticket_number','patient_visit_id','queue_type_id','clinician_id','priority','called_at','called_by','skip_reason','queue_entry_id','from_status','to_status','changed_by','from_queue_type_id','to_queue_type_id'])]
class QueueStatusHistory extends Model
{
    protected $casts = ['active' => 'boolean', 'arrived_at' => 'datetime', 'checked_in_at' => 'datetime', 'service_started_at' => 'datetime', 'completed_at' => 'datetime', 'called_at' => 'datetime'];

    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function visit(): BelongsTo { return $this->belongsTo(PatientVisit::class, 'patient_visit_id'); }
    public function queueType(): BelongsTo { return $this->belongsTo(QueueType::class); }
    public function clinician(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'clinician_id'); }
    public function statusHistories(): HasMany { return $this->hasMany(QueueStatusHistory::class, 'queue_entry_id'); }
    public function transfers(): HasMany { return $this->hasMany(QueueTransfer::class, 'queue_entry_id'); }
}
