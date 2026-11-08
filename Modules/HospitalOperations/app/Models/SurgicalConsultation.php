<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_case_id','presenting_complaint','clinical_history','previous_treatment','examination_summary','indication','proposed_procedure','alternatives','risks','expected_benefits','recommendation','decision','decision_at','clinician_id','updated_by'])]
class SurgicalConsultation extends Model
{
    protected $casts = ['decision_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function clinician(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'clinician_id'); }
    public function versions(): HasMany { return $this->hasMany(SurgicalConsultationVersion::class); }
}
