<?php

namespace Modules\Foundation\Support;

use App\Models\User;
use Modules\Foundation\Models\Branch;

class BranchContext
{
    public static function current(?User $user = null): ?Branch
    {
        $user ??= auth()->user();
        if (! $user) {
            return null;
        }

        $selected = session('current_branch_id');
        $allowed = static::allowed($user);

        if ($selected && $allowed->contains('id', $selected)) {
            return $allowed->firstWhere('id', $selected);
        }

        return $user->primaryBranch ?? $allowed->first();
    }

    public static function allowed(User $user): \Illuminate\Support\Collection
    {
        if ($user->hasRole('Super Admin')) {
            return Branch::orderBy('name')->get();
        }

        return $user->branches()->where('status', 'active')->get();
    }

    public static function canAccess(User $user, int $branchId): bool
    {
        return static::allowed($user)->contains('id', $branchId);
    }
}
