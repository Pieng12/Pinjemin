<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Item $item): bool
    {
        return $this->owns($user, $item) || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    public function manage(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    public function moderate(User $user, Item $item): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Item $item): bool
    {
        return $item->user_id === $user->id;
    }
}
