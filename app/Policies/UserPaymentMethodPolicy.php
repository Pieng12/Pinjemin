<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPaymentMethod;

class UserPaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UserPaymentMethod $method): bool
    {
        return $user->id === $method->user_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, UserPaymentMethod $method): bool
    {
        return $user->id === $method->user_id;
    }

    public function delete(User $user, UserPaymentMethod $method): bool
    {
        return $user->id === $method->user_id;
    }

    public function restore(User $user, UserPaymentMethod $method): bool
    {
        return false;
    }

    public function forceDelete(User $user, UserPaymentMethod $method): bool
    {
        return false;
    }
}
