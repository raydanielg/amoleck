<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_operation_id','type','phase','status','discrepancy_notes','resolution','resolved_by','resolved_at','counted_by','verified_by'])]
class SurgicalCount extends Model
{
    protected $casts = ['resolved_at' => 'datetime'];
    public function operation(): BelongsTo { return $this->belongsTo(SurgicalOperation::class); }
    public function items(): HasMany { return $this->hasMany(SurgicalCountItem::class); }
}
