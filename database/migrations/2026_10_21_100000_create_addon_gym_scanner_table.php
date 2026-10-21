<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A dispenser or area-control device may now settle against several usage
     * add-ons — e.g. the local drink package plus the one sold at a sibling
     * location whose members are admitted here.
     *
     * gym_scanners.addon_id stays in place and keeps holding the first linked
     * add-on, so code and configs that still read the single link go on
     * working. The existing links are copied over as the initial set.
     */
    public function up(): void
    {
        Schema::create('addon_gym_scanner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_scanner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['gym_scanner_id', 'addon_id']);
        });

        $now = now();

        DB::table('gym_scanners')
            ->whereNotNull('addon_id')
            ->orderBy('id')
            ->select(['id', 'addon_id'])
            ->chunk(500, function ($scanners) use ($now) {
                DB::table('addon_gym_scanner')->insert(
                    $scanners->map(fn ($scanner) => [
                        'gym_scanner_id' => $scanner->id,
                        'addon_id' => $scanner->addon_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_gym_scanner');
    }
};
