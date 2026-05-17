<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->string('google_place_id');
            $table->string('venue_name');
            $table->string('participant_name')->nullable();
            $table->enum('vote', ['up', 'down']);
            $table->timestamps();

            $table->unique(['session_id', 'google_place_id', 'participant_name'], 'unique_vote_per_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_votes');
    }
};
