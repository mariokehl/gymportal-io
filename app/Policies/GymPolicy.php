<?php

namespace App\Policies;

use App\Models\Gym;
use App\Models\User;

class GymPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * Any member (owner or via gym_users) may view a gym — this is what allows
     * a trainer or staff member to switch into and open an organization they do
     * not own. Strangers (no relationship at all) are still denied.
     */
    public function view(User $user, Gym $gym): bool
    {
        return $user->belongsToGym($gym);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->current_gym_id > 0;
    }

    /**
     * Determine whether the user can update the model.
     *
     * Management ability: owners and admins of the gym. Staff and trainers are
     * denied.
     */
    public function update(User $user, Gym $gym): bool
    {
        return $user->canManageGym($gym);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Deleting an organization is reserved for its owner.
     */
    public function delete(User $user, Gym $gym): bool
    {
        return $user->id === $gym->owner_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Gym $gym): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Gym $gym): bool
    {
        return false;
    }

    /**
     * Determine whether the user can manage the gym (scanners, settings, etc.).
     *
     * Management is gated on the user's per-organization role: only owners and
     * admins of this specific gym may manage it. Staff and trainers are denied,
     * as are users with no relationship to the gym.
     */
    public function manage(User $user, Gym $gym): bool
    {
        return $user->canManageGym($gym);
    }
}
