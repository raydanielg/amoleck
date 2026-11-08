<?php

namespace Modules\Foundation\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SuperAdminGuard
{
    public const PROTECTED_ROLES = ['Super Admin'];

    public static function ensureNotLastSuperAdmin(?User $except = null): void
    {
        $query = User::role('Super Admin')->where('status', 'active');
        if ($except) {
            $query->where('id', '!=', $except->id);
        }

        if ($query->count() === 0) {
            throw ValidationException::withMessages([
                'user' => 'The last active Super Admin cannot be removed or deactivated.',
            ]);
        }
    }
}
