<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['surgical_case_id','surgical_booking_id','user_id','employee_id','role','branch_id','status','confirmed_at','replaced_by_id','assigned_by'])]
class SurgicalTeamAssignment extends Model
{
    public const ROLES = ['lead_surgeon','assistant_surgeon','anesthetist','anesthesia_assistant','scrub_nurse','circulating_nurse','theatre_nurse','recovery_nurse','other'];
    protected $casts = ['confirmed_at' => 'datetime'];
    public function case(): BelongsTo { return $this->belongsTo(SurgicalCase::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function employee(): BelongsTo { return $this->belongsTo(\Modules\Administration\Models\Employee::class); }
    public function histories(): HasMany { return $this->hasMany(SurgicalTeamAssignmentHistory::class); }
}
