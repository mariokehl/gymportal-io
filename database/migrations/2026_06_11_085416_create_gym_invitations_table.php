<?php

use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Gym::class)->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role'); // admin, staff, trainer
            $table->string('token', 64)->unique();
            $table->foreignIdFor(User::class, 'invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // At most one pending invitation per email per gym.
            $table->unique(['gym_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_invitations');
    }
};
