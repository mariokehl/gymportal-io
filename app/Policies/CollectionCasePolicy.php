<?php

namespace App\Policies;

use App\Models\CollectionCase;
use App\Models\User;

class CollectionCasePolicy
{
    /**
     * Collection cases carry financial and personal data, so only owners and
     * admins of the currently selected gym may access them.
     */
    protected function canManageCurrentGym(User $user): bool
    {
        $gym = $user->currentGym;

        return $gym !== null && $user->canManageGym($gym);
    }

    public function viewAny(User $user): bool
    {
        return $this->canManageCurrentGym($user);
    }

    public function view(User $user, CollectionCase $case): bool
    {
        return $this->canManageCurrentGym($user) && $user->current_gym_id === $case->gym_id;
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentGym($user);
    }

    public function update(User $user, CollectionCase $case): bool
    {
        return $this->view($user, $case);
    }

    public function delete(User $user, CollectionCase $case): bool
    {
        return $this->view($user, $case);
    }
}
