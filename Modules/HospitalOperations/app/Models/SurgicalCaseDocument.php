<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_case_id','patient_document_id','category','description','path','original_name','mime','uploaded_by'])]
class SurgicalCaseDocument extends Model
{
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
}
