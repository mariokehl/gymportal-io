<?php

namespace App\Listeners;

use App\Events\MemberRegistered;
use App\Notifications\MemberRegisteredNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;

class SendMemberRegisteredNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MemberRegistered $event): void
    {
        try {
            // Notify all users of the gym who are not deleted and not blocked
            $gymUsers = User::where('current_gym_id', $event->gym->id)
                ->where('is_blocked', false)
                ->whereNull('deleted_at')
                ->get();

            // Add the gym owner if they haven't selected this gym as their current gym
            if ($event->gym->owner_id && !$gymUsers->contains('id', $event->gym->owner_id)) {
                $owner = User::where('id', $event->gym->owner_id)
                    ->where('is_blocked', false)
                    ->whereNull('deleted_at')
                    ->first();

                if ($owner) {
                    $gymUsers->push($owner);
                }
            }

            foreach ($gymUsers as $user) {
                $user->notify(new MemberRegisteredNotification(
                    $event->member,
                    $event->membership,
                    $event->gym,
                    $event->registrationSource
                ));
            }

            Log::info('Member registered notification sent', [
                'member_id' => $event->member->id,
                'gym_id' => $event->gym->id,
                'notified_users' => $gymUsers->count(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send member registered notification', [
                'member_id' => $event->member->id,
                'gym_id' => $event->gym->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
