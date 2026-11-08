<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_case_id','patient_id','branch_id','type','surgical_procedure_id','clinician_id','consented_at','information_explained','risks_alternatives','decision','representative_name','representative_relationship','witness_name','interpreter_name','document_path','status','status_reason','withdrawn_at','withdrawn_by','withdrawal_reason','version','recorded_by'])]
class SurgicalConsent extends Model
{
    protected $casts = ['consented_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class, 'surgical_case_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function versions(): HasMany { return $this->hasMany(SurgicalConsentVersion::class); }
}
