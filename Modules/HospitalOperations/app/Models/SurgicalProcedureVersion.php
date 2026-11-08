<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_procedure_id','version','snapshot','action','acted_by'])]
class SurgicalProcedureVersion extends Model
{
    protected $casts = ['snapshot' => 'array'];
    public function procedure(): BelongsTo { return $this->belongsTo(SurgicalProcedure::class); }
    public function actor(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'acted_by'); }
}
