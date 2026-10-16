<?php

namespace App\Policies;

use App\Models\GuestProduct;
use App\Models\User;

class GuestProductPolicy
{
    public function create(User $user): bool
    {
        return $user->currentGym !== null && $user->canManageGym($user->currentGym);
    }

    public function update(User $user, GuestProduct $product): bool
    {
        return $user->current_gym_id === $product->gym_id
            && $user->canManageGym($product->gym);
    }
}
