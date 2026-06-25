<?php

namespace App\Policies;

use App\Models\Addon;
use App\Models\User;

class AddonPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Addon $addon): bool
    {
        return $user->current_gym_id === $addon->gym_id;
    }

    public function create(User $user): bool
    {
        return (bool) $user->current_gym_id;
    }

    public function update(User $user, Addon $addon): bool
    {
        return $user->current_gym_id === $addon->gym_id;
    }

    public function delete(User $user, Addon $addon): bool
    {
        return $user->current_gym_id === $addon->gym_id;
    }

    public function restore(User $user, Addon $addon): bool
    {
        return false;
    }

    public function forceDelete(User $user, Addon $addon): bool
    {
        return false;
    }
}
