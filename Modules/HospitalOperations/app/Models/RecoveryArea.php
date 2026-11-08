<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','branch_id','capacity','active'])]
class RecoveryArea extends Model
{
    protected $casts = ['active' => 'boolean'];
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function beds(): HasMany { return $this->hasMany(RecoveryBed::class); }
}
