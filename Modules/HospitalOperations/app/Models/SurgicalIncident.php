<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_case_id','surgical_operation_id','patient_id','branch_id','category','event_at','description','immediate_actions','severity','escalation','status','reported_by','reviewed_by'])]
class SurgicalIncident extends Model
{
    protected $casts = ['event_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class, 'surgical_case_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
}
