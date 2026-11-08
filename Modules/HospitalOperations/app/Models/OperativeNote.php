<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_operation_id','surgical_case_id','preop_diagnosis','postop_diagnosis','findings','technique','narrative','instructions','status','version','author_id','reviewed_by','finalized_at'])]
class OperativeNote extends Model
{
    protected $casts = ['finalized_at' => 'datetime'];
    public function operation(): BelongsTo { return $this->belongsTo(SurgicalOperation::class); }
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class, 'surgical_case_id'); }
    public function author(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'author_id'); }
    public function versions(): HasMany { return $this->hasMany(OperativeNoteVersion::class); }
}
