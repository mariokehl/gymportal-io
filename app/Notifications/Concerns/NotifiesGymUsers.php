<?php

namespace App\Notifications\Concerns;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait NotifiesGymUsers
{
    protected function notifyGymUsers(Gym $gym, Notification $notification, array $logContext = []): void
    {
        $gymUsers = $this->getNotifiableGymUsers($gym);

        foreach ($gymUsers as $user) {
            $user->notify($notification);
        }

        Log::info(class_basename($notification) . ' sent', array_merge([
            'gym_id' => $gym->id,
            'notified_users' => $gymUsers->count(),
        ], $logContext));
    }

    protected function getNotifiableGymUsers(Gym $gym): Collection
    {
        $gymUsers = User::where('current_gym_id', $gym->id)
            ->where('is_blocked', false)
            ->whereNull('deleted_at')
            ->get();

        if ($gym->owner_id && !$gymUsers->contains('id', $gym->owner_id)) {
            $owner = User::where('id', $gym->owner_id)
                ->where('is_blocked', false)
                ->whereNull('deleted_at')
                ->first();

            if ($owner) {
                $gymUsers->push($owner);
            }
        }

        return $gymUsers;
    }
}
