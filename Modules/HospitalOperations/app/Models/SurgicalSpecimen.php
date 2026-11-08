<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['specimen_number','surgical_operation_id','surgical_case_id','patient_id','collected_at','collected_by','description','anatomical_source','laterality','container','label','destination','laboratory_order_id','status'])]
class SurgicalSpecimen extends Model
{
    protected $casts = ['collected_at' => 'datetime'];
    public function operation(): BelongsTo { return $this->belongsTo(SurgicalOperation::class); }
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function handovers(): HasMany { return $this->hasMany(SurgicalSpecimenHandover::class); }
}
