<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_case_id','from_status','to_status','reason','approval_reference','changed_by'])]
class SurgicalCaseStatusHistory extends Model
{
    protected $table = 'surgical_case_status_histories';
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function actor(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'changed_by'); }
}
