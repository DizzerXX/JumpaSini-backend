<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->timestamp('voting_starts_at')->nullable()->after('session_confirmed_at');
            $table->timestamp('voting_ends_at')->nullable()->after('voting_starts_at');
            $table->string('voting_confirmed_venue')->nullable()->after('voting_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['voting_starts_at', 'voting_ends_at', 'voting_confirmed_venue']);
        });
    }
};
