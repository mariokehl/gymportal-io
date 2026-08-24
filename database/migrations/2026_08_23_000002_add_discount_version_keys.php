<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Give every saved discount ladder a version key, and record on each
     * membership which version it was signed under.
     *
     * The membership already carries its own copy of the phases, so billing
     * does not need this. The version is the audit trail: it ties a charge
     * back to the exact ladder the operator had configured at signup, which a
     * copied row alone cannot prove.
     *
     * Plan phases are soft-deleted from here on, so a version stays resolvable
     * after the operator rewrites the promotion.
     */
    public function up(): void
    {
        Schema::table('membership_plan_discount_phases', function (Blueprint $table) {
            $table->uuid('version_key')->nullable()->after('membership_plan_id');
            $table->softDeletes();

            $table->index('version_key');
        });

        Schema::table('membership_discount_phases', function (Blueprint $table) {
            $table->uuid('version_key')->nullable()->after('membership_id');

            $table->index('version_key');
        });

        // Stamp the ladders that already exist: all phases of one plan were
        // written by a single sync(), so they share one version.
        $planIds = DB::table('membership_plan_discount_phases')
            ->distinct()
            ->pluck('membership_plan_id');

        foreach ($planIds as $planId) {
            $versionKey = (string) Str::uuid();

            DB::table('membership_plan_discount_phases')
                ->where('membership_plan_id', $planId)
                ->update(['version_key' => $versionKey]);

            // Memberships signed on that plan carry the same ladder.
            DB::table('membership_discount_phases')
                ->whereIn('membership_id', function ($query) use ($planId) {
                    $query->select('id')
                        ->from('memberships')
                        ->where('membership_plan_id', $planId);
                })
                ->update(['version_key' => $versionKey]);
        }
    }

    public function down(): void
    {
        Schema::table('membership_discount_phases', function (Blueprint $table) {
            $table->dropIndex(['version_key']);
            $table->dropColumn('version_key');
        });

        Schema::table('membership_plan_discount_phases', function (Blueprint $table) {
            $table->dropIndex(['version_key']);
            $table->dropColumn(['version_key', 'deleted_at']);
        });
    }
};
