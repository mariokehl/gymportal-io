<?php

namespace App\Policies;

use App\Models\CollectionRun;
use App\Models\User;

class CollectionRunPolicy
{
    /**
     * Collection runs are a financial tool, so only owners and admins of the
     * currently selected gym may access them.
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

    public function view(User $user, CollectionRun $run): bool
    {
        return $this->canManageCurrentGym($user) && $user->current_gym_id === $run->gym_id;
    }

    public function create(User $user): bool
    {
        return $this->canManageCurrentGym($user);
    }

    public function update(User $user, CollectionRun $run): bool
    {
        return $this->view($user, $run);
    }

    public function delete(User $user, CollectionRun $run): bool
    {
        return $this->view($user, $run);
    }
}
