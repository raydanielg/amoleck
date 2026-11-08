<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_case_id','surgical_checklist_template_id','stage','item_key','label','mandatory','required_role','status','evidence','notes','completed_by','completed_at'])]
class SurgicalChecklistItem extends Model
{
    protected $casts = ['mandatory' => 'boolean', 'completed_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class, 'surgical_case_id'); }
    public function completer(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'completed_by'); }
}
