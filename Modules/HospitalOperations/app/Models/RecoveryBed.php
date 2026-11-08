<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code','recovery_area_id','branch_id','status','cleaning_since'])]
class RecoveryBed extends Model
{
    protected $casts = ['cleaning_since' => 'datetime'];
    public function area(): BelongsTo { return $this->belongsTo(RecoveryArea::class,'recovery_area_id'); }
}
