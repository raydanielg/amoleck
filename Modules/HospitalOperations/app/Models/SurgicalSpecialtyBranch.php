<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_specialty_id','branch_id','available'])]
class SurgicalSpecialtyBranch extends Model
{
    protected $table = 'surgical_specialty_branch';
    protected $casts = ['available' => 'boolean'];
    public function specialty(): BelongsTo { return $this->belongsTo(SurgicalSpecialty::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
}
