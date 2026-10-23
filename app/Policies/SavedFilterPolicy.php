<?php

namespace App\Policies;

use App\Models\SavedFilter;
use App\Models\User;

class SavedFilterPolicy
{
    /**
     * Saved filters are shared by the staff of a gym.
     */
    public function update(User $user, SavedFilter $savedFilter): bool
    {
        return $user->current_gym_id === $savedFilter->gym_id;
    }

    public function delete(User $user, SavedFilter $savedFilter): bool
    {
        return $user->current_gym_id === $savedFilter->gym_id;
    }
}
