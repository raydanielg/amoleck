<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_operation_id','surgical_case_id','patient_id','anesthetist_id','technique','started_at','ended_at','observations','airway_management','fluids','adverse_events','interventions','clinical_notes','handover_notes','status','version'])]
class AnesthesiaRecord extends Model
{
    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    public function operation(): BelongsTo { return $this->belongsTo(SurgicalOperation::class); }
    public function anesthetist(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'anesthetist_id'); }
    public function medicationRefs(): HasMany { return $this->hasMany(AnesthesiaMedicationRef::class); }
}
