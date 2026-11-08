<?php

namespace Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'address', 'region', 'district', 'phone', 'email', 'manager_name', 'status', 'opening_date', 'working_hours', 'settings', 'logo', 'description', 'ward', 'created_by', 'updated_by'])]
class Branch extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'temporarily_closed'];

    protected $casts = ['opening_date' => 'date', 'working_hours' => 'array', 'settings' => 'array'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_primary')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
