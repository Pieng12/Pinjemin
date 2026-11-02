<?php

namespace App\Policies;

use App\Models\IdentityVerification;
use App\Models\User;

class IdentityVerificationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, IdentityVerification $identityVerification): bool
    {
        return $user->isAdmin() || $user->id === $identityVerification->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IdentityVerification $identityVerification): bool
    {
        return $user->isAdmin();
    }

    public function withdraw(User $user, IdentityVerification $identityVerification): bool
    {
        return $user->id === $identityVerification->user_id && in_array($identityVerification->status, ['pending', 'verified'], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IdentityVerification $identityVerification): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, IdentityVerification $identityVerification): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, IdentityVerification $identityVerification): bool
    {
        return false;
    }
}
