<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','description','department_id','head_user_id','required_clinician_categories','effective_from','effective_to','status','created_by'])]
class SurgicalSpecialty extends Model
{
    protected $table = 'surgical_specialties';
    protected $casts = ['required_clinician_categories' => 'array', 'effective_from' => 'date', 'effective_to' => 'date'];
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function head(): BelongsTo { return $this->belongsTo(\App\Models\User::class,'head_user_id'); }
    public function branches(): HasMany { return $this->hasMany(SurgicalSpecialtyBranch::class); }
    public function procedures(): HasMany { return $this->hasMany(SurgicalProcedure::class); }
}
