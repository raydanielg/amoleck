<?php

namespace Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;

#[Fillable(['identifier', 'name', 'description', 'version', 'category', 'icon', 'status', 'dependencies', 'features', 'is_protected', 'registered_by'])]
class SystemModule extends Model
{
    public const STATUSES = ['active', 'disabled', 'maintenance', 'pending_installation', 'unavailable'];

    protected $table = 'modules';
    protected $casts = ['dependencies' => 'array', 'features' => 'array', 'is_protected' => 'boolean'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'module_role', 'module_id', 'role_id');
    }
}
