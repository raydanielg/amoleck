<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','category','description','head_id','parent_id','contact','operating_hours','status','department_id','branch_id','active','service_category_id','type','default_duration','clinician_category','requires_appointment','allows_walkin','requires_referral','requires_encounter','workflow_mapping','clinical_service_id','available','operating_days','duration_override','user_id','is_primary','effective_from','effective_to','display_order','version','snapshot','action','acted_by'])]
class ServiceBranchConfig extends Model
{
    protected $casts = ['active' => 'boolean', 'available' => 'boolean', 'requires_appointment' => 'boolean', 'allows_walkin' => 'boolean', 'requires_referral' => 'boolean', 'requires_encounter' => 'boolean', 'is_primary' => 'boolean', 'operating_days' => 'array', 'effective_from' => 'date', 'effective_to' => 'date', 'snapshot' => 'array'];

    public function parent(): BelongsTo { return $this->belongsTo(static::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(static::class, 'parent_id'); }
    public function head(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'head_id'); }
    public function branches(): BelongsToMany { return $this->belongsToMany(\Modules\Foundation\Models\Branch::class, 'department_branch')->withPivot(['operating_hours','active'])->withTimestamps(); }
    public function category(): BelongsTo { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function branchConfigs(): HasMany { return $this->hasMany(ServiceBranchConfig::class, 'clinical_service_id'); }
    public function clinicians(): HasMany { return $this->hasMany(ServiceClinicianAssignment::class, 'clinical_service_id'); }
    public function versions(): HasMany { return $this->hasMany(ServiceVersion::class, 'clinical_service_id'); }
}
