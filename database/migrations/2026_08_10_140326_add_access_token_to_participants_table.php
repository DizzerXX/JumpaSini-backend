<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            // Private token issued once, at creation, and returned only in that
            // response. Required to prove ownership when removing a participant.
            $table->string('access_token', 64)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
    }
};
