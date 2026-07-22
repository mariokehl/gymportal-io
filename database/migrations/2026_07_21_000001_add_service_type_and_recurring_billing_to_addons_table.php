<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            // 'additional' = one-off service such as a trainer induction.
            // 'usage' = consumable service (drinks, sauna) with a quota that is
            // settled through a device (dispenser).
            $table->string('service_type')->default('additional')->after('description');

            // 'one_time' = charged once at contract start.
            // 'recurring' = charged monthly, in sync with the membership fee.
            $table->string('billing_type')->default('one_time')->after('service_type');

            // Recurring add-ons only: grants the remainder of the current month
            // for free, the paid term then starts on the 1st of the next month.
            $table->boolean('trial_rest_of_month')->default(false)->after('billing_type');

            // Usage services only: how long a single usage is valid.
            // 'single' = consumed per use, 'fixed_period' = a defined duration,
            // 'full_day' = valid for the whole day.
            $table->string('usage_period')->nullable()->after('trial_rest_of_month');

            // Usage services with usage_period = 'fixed_period': duration of one
            // usage, e.g. 2 hours.
            $table->unsignedInteger('usage_duration')->nullable()->after('usage_period');
            $table->string('usage_duration_unit')->nullable()->after('usage_duration');

            // Usage services only: null quota means unlimited (flat rate),
            // otherwise the number of units per quota interval.
            $table->unsignedInteger('quota_amount')->nullable()->after('usage_duration_unit');
            $table->string('quota_interval')->nullable()->after('quota_amount');

            // Usage services only: whether usage is booked through a device.
            $table->boolean('settled_via_device')->default(false)->after('quota_interval');

            $table->index(['gym_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'service_type']);

            $table->dropColumn([
                'service_type',
                'billing_type',
                'trial_rest_of_month',
                'usage_period',
                'usage_duration',
                'usage_duration_unit',
                'quota_amount',
                'quota_interval',
                'settled_via_device',
            ]);
        });
    }
};
