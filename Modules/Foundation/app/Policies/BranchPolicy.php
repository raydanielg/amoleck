<?php

namespace Modules\Foundation\Policies;

use App\Models\User;
use Modules\Foundation\Models\Branch;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.edit');
    }

    public function activate(User $user, Branch $branch): bool
    {
        return $user->can('branches.activate');
    }

    public function deactivate(User $user, Branch $branch): bool
    {
        return $user->can('branches.deactivate');
    }
}
