<?php

namespace App\Policies;

use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MembershipPlanPolicy
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
     */
    public function view(User $user, MembershipPlan $membershipPlan): bool
    {
        return $user->current_gym_id === $membershipPlan->gym_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->current_gym_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MembershipPlan $membershipPlan): bool
    {
        return $user->current_gym_id === $membershipPlan->gym_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MembershipPlan $membershipPlan): bool
    {
        return $user->current_gym_id === $membershipPlan->gym_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MembershipPlan $membershipPlan): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MembershipPlan $membershipPlan): bool
    {
        return false;
    }
}
