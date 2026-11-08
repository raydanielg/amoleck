<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_case_id','branch_id','product_type','units','status','notes','requested_by','confirmed_by','confirmed_at'])]
class SurgicalBloodRequirement extends Model
{
    protected $casts = ['confirmed_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
}
