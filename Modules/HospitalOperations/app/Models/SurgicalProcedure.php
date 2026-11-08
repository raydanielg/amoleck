<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','surgical_specialty_id','description','category','urgency_classification','expected_duration_minutes','theatre_category','required_team_roles','required_equipment_categories','required_consumables','anesthesia_options','preop_template','consent_required','recovery_requirements','version','status','created_by'])]
class SurgicalProcedure extends Model
{
    protected $casts = ['required_team_roles' => 'array', 'required_equipment_categories' => 'array', 'required_consumables' => 'array', 'anesthesia_options' => 'array', 'preop_template' => 'array', 'recovery_requirements' => 'array', 'consent_required' => 'boolean'];
    public function specialty(): BelongsTo { return $this->belongsTo(SurgicalSpecialty::class); }
    public function versions(): HasMany { return $this->hasMany(SurgicalProcedureVersion::class); }
    public function branches(): HasMany { return $this->hasMany(SurgicalProcedureBranch::class); }
}
