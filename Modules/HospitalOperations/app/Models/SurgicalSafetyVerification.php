<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_case_id','surgical_operation_id','stage','status','performed_by','performed_at','participant_ids','notes'])]
class SurgicalSafetyVerification extends Model
{
    protected $casts = ['performed_at' => 'datetime', 'participant_ids' => 'array'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class, 'surgical_case_id'); }
    public function items(): HasMany { return $this->hasMany(SurgicalSafetyVerificationItem::class); }
    public function performer(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'performed_by'); }
}
