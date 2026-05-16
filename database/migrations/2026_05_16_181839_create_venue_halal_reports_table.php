<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_halal_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('venue_id')->nullable()->index();
            $table->string('google_place_id')->nullable()->index();
            $table->boolean('reported_as_halal');
            $table->uuid('session_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_halal_reports');
    }
};
