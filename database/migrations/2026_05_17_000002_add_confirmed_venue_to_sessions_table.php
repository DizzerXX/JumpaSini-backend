<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('session_confirmed_venue')->nullable()->after('status');
            $table->timestamp('session_confirmed_at')->nullable()->after('session_confirmed_venue');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['session_confirmed_venue', 'session_confirmed_at']);
        });
    }
};
