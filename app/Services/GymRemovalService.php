<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GymRemovalService
{
    /**
     * Sensitive gym attributes that are cleared before the gym is soft-deleted.
     */
    public const SENSITIVE_ATTRIBUTES = [
        'account_holder',
        'iban',
        'bic',
        'creditor_identifier',
        'api_key',
        'api_key_generated_at',
        'mollie_config',
        'scanner_secret_key',
        'checkin_station_token',
    ];

    public function __construct(
        private GymDataImportService $gymDataImportService,
    ) {}

    /**
     * Remove a gym together with all of its data and move the owner to
     * another owned gym (or none, if this was the last one).
     */
    public function remove(User $owner, Gym $gym): void
    {
        $mollieConfig = $gym->mollie_config ?? [];

        DB::transaction(function () use ($owner, $gym) {
            // Member data first: also removes the members' Mollie customers,
            // which needs the payment methods and the Mollie config of the gym
            $this->gymDataImportService->deleteAllGymMemberData($gym->id);

            // Remaining gym data (courses, plans, templates, legal URLs, scanners, ...)
            $this->gymDataImportService->deleteExistingData($gym->id);

            if ($owner->current_gym_id === $gym->id) {
                $nextGym = $owner->ownedGyms()
                    ->whereKeyNot($gym->id)
                    ->first();

                $owner->update(['current_gym_id' => $nextGym?->id]);
            }

            // The gym row is only soft-deleted, so wipe credentials and bank data
            $gym->update(array_fill_keys(self::SENSITIVE_ATTRIBUTES, null));

            $gym->delete();
        });

        // Only after the commit: a rolled back removal must keep a working webhook
        MollieService::deleteWebhookIfAny($mollieConfig, $gym->id);
    }
}
