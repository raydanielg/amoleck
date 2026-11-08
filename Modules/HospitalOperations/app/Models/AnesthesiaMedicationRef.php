<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['anesthesia_record_id','medication_administration_id','notes'])]
class AnesthesiaMedicationRef extends Model
{
    public function administration(): BelongsTo { return $this->belongsTo(MedicationAdministration::class,'medication_administration_id'); }
}
