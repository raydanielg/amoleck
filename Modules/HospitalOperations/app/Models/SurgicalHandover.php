<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_case_id','surgical_operation_id','patient_id','branch_id','operation_summary','procedure_performed','anesthesia_summary','medications_summary','observations','allergies','complications','outstanding_investigations','precautions','pending_actions','destination_type','destination_ward_id','destination_bed_id','admission_id','sent_by','sent_at','received_by','received_at','status'])]
class SurgicalHandover extends Model
{
    protected $casts = ['sent_at' => 'datetime', 'received_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function sender(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'sent_by'); }
    public function receiver(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'received_by'); }
    public function ward(): BelongsTo { return $this->belongsTo(Ward::class,'destination_ward_id'); }
    public function bed(): BelongsTo { return $this->belongsTo(Bed::class,'destination_bed_id'); }
}
