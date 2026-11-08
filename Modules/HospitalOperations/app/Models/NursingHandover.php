<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['admission_id','patient_id','nurse_id','branch_id','ward_id','type','starts_at','ends_at','assigned_by','active','kind','value_num','value_text','unit','method','source','taken_at','notes','recorded_by','corrected_by','correction_reason','nurse_id','category','shift','subjective','objective','assessment','actions','response','status','finalized_at','nursing_note_id','version','snapshot','action','reason','acted_by','direction','amount','recorded_at','concern','observation','reported_by','clinician_id','contact_method','escalated_at','acknowledged_at','acknowledged_by','instructions','outgoing_shift','incoming_shift','summary','patients','prepared_by'])]
class NursingHandover extends Model
{
    protected $casts = ['active' => 'boolean', 'taken_at' => 'datetime', 'recorded_at' => 'datetime', 'escalated_at' => 'datetime', 'acknowledged_at' => 'datetime', 'finalized_at' => 'datetime', 'patients' => 'array', 'snapshot' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function nurse(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'nurse_id'); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'recorded_by'); }
    public function versions(): HasMany { return $this->hasMany(NursingNoteVersion::class); }
}
