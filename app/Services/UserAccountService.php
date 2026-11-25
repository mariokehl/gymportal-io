<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserAccountService
{
    public function __construct(
        private GymRemovalService $gymRemovalService,
    ) {}

    /**
     * Block the user. Active sessions are terminated by the blocked.check
     * middleware on the next request.
     */
    public function block(User $user, ?string $reason, User $admin): void
    {
        $user->update([
            'is_blocked' => true,
            'blocked_reason' => $reason,
        ]);

        Log::info('User blocked by admin', [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
        ]);
    }

    public function unblock(User $user, User $admin): void
    {
        $user->update([
            'is_blocked' => false,
            'blocked_reason' => null,
        ]);

        Log::info('User unblocked by admin', [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Remove all gyms owned by the user via the regular gym removal and
     * soft-delete the user afterwards.
     *
     * Each gym is removed in its own transaction (it also deletes Mollie
     * customers, which cannot be rolled back). If a later step fails, the
     * already removed gyms stay removed and the deletion can simply be retried.
     */
    public function delete(User $user, User $admin): void
    {
        $user->ownedGyms()->get()->each(
            fn ($gym) => $this->gymRemovalService->remove($user, $gym)
        );

        DB::transaction(function () use ($user) {
            // Memberships in gyms of other owners
            DB::table('gym_users')->where('user_id', $user->id)->delete();

            $user->update(['current_gym_id' => null]);
            $user->delete();
        });

        Log::info('User deleted by admin', [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
        ]);
    }
}
