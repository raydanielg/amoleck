<?php

namespace Modules\PatientCare\Services;

use App\Models\User;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Models\Patient;

class PatientAccessService
{
    /** Can this user access this patient record? */
    public function canAccess(User $user, Patient $patient): bool
    {
        if ($user->hasRole('Super Admin') || $user->can('patientcare.patients.view_all_branches')) {
            return true;
        }

        $allowed = BranchContext::allowed($user)->pluck('id');

        return $patient->primary_branch_id && $allowed->contains($patient->primary_branch_id)
            || $patient->branches()->whereIn('branches.id', $allowed)->exists()
            || $patient->registration_branch_id && $allowed->contains($patient->registration_branch_id);
    }

    public function scopeFor(User $user, $query)
    {
        if ($user->hasRole('Super Admin') || $user->can('patientcare.patients.view_all_branches')) {
            return $query;
        }
        $allowed = BranchContext::allowed($user)->pluck('id')->all() ?: [-1];

        return $query->where(function ($q) use ($allowed) {
            $q->whereIn('primary_branch_id', $allowed)
                ->orWhereIn('registration_branch_id', $allowed)
                ->orWhereHas('branches', fn ($b) => $b->whereIn('branches.id', $allowed));
        });
    }
}
