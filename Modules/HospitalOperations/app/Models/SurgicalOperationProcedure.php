<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_operation_id','surgical_procedure_id','procedure_name','kind','surgeon_id','notes','sort_order'])]
class SurgicalOperationProcedure extends Model
{
    public function operation(): BelongsTo { return $this->belongsTo(SurgicalOperation::class); }
    public function procedure(): BelongsTo { return $this->belongsTo(SurgicalProcedure::class,'surgical_procedure_id'); }
}
