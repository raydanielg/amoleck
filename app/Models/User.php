<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Modules\Foundation\Models\Branch;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $employee_number
 * @property string|null $username
 * @property string|null $phone
 * @property string|null $job_title
 * @property string|null $department
 * @property string $status
 * @property Carbon|null $last_login_at
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property int|null $primary_branch_id
 * @property \Modules\Foundation\Models\Branch|null $primaryBranch
 */
#[Fillable(['name', 'employee_number', 'email', 'phone', 'password', 'job_title', 'department', 'status', 'username', 'gender', 'date_of_birth', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'employment_type', 'employment_start_date', 'profile_photo', 'primary_branch_id', 'last_login_at', 'created_by', 'updated_by'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    public const STATUSES = ['active', 'inactive', 'suspended', 'locked'];

    /** @return BelongsToMany<\Modules\Foundation\Models\Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_primary')->withTimestamps();
    }

    /** @return BelongsTo<\Modules\Foundation\Models\Branch, $this> */
    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'employment_start_date' => 'date',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
