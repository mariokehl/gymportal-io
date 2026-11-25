<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Role of the platform administrators (see ImpersonationController).
     */
    public const ADMIN_ROLE_ID = 1;

    /**
     * Platform admins may block other users, but never themselves or other admins.
     */
    public function block(User $admin, User $user): bool
    {
        return $this->managesOtherUser($admin, $user);
    }

    /**
     * Platform admins may delete other users, but never themselves or other admins.
     */
    public function delete(User $admin, User $user): bool
    {
        return $this->managesOtherUser($admin, $user);
    }

    private function managesOtherUser(User $admin, User $user): bool
    {
        return $admin->role_id === self::ADMIN_ROLE_ID
            && $user->role_id !== self::ADMIN_ROLE_ID
            && $admin->id !== $user->id;
    }
}
