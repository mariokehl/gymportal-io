<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Notification channel for users
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Gym-wide notifications
Broadcast::channel('gym.{gymId}', function ($user, $gymId) {
    return $user->current_gym_id === (int) $gymId;
});

// Scanner access logs channel (live updates for access control dashboard)
Broadcast::channel('gym.{gymId}.access-logs', function ($user, $gymId) {
    return $user->current_gym_id === (int) $gymId;
});
