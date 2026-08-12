<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // scopeActive() and markExpired() filter on these on nearly every request.
            $table->index('expires_at');
            $table->index('status');
        });

        Schema::table('participants', function (Blueprint $table) {
            // foreignUuid()->constrained() creates the FK constraint but, on Postgres,
            // no supporting index — unlike MySQL/InnoDB, which indexes FKs automatically.
            $table->index('session_id');
        });

        Schema::table('venues', function (Blueprint $table) {
            // findNearMidpoint() filters by type + is_active before ranking by distance.
            $table->index(['type', 'is_active']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['status']);
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex(['type', 'is_active']);
            $table->dropIndex(['latitude', 'longitude']);
        });
    }
};
