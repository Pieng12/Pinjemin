<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChatConversationPolicy
{
    public function view(User $user, ChatConversation $conversation): Response
    {
        return in_array($user->id, [$conversation->renter_id, $conversation->owner_id], true)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
