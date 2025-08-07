<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentMethodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->current_gym_id === $paymentMethod->member->gym->id;
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
     */
    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->current_gym_id === $paymentMethod->member->gym->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return false;
    }
}
