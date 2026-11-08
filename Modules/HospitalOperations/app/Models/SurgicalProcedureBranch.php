<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_procedure_id','branch_id','available'])]
class SurgicalProcedureBranch extends Model
{
    protected $table = 'surgical_procedure_branch';
    protected $casts = ['available' => 'boolean'];
    public function procedure(): BelongsTo { return $this->belongsTo(SurgicalProcedure::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
}
