<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Votes were previously keyed by free-text participant_name, which collides for
     * anyone voting without a name (every anonymous voter matched the same row and
     * silently overwrote the previous vote). Key on the participant's UUID instead —
     * a stable identity the clients already hold.
     */
    public function up(): void
    {
        Schema::table('venue_votes', function (Blueprint $table) {
            $table->uuid('participant_id')->nullable()->after('session_id');

            $table->foreign('participant_id')
                  ->references('id')
                  ->on('participants')
                  ->cascadeOnDelete();
        });

        Schema::table('venue_votes', function (Blueprint $table) {
            $table->dropUnique('unique_vote_per_participant');

            // Legacy rows keep participant_id = NULL. Postgres treats NULLs as distinct
            // in a unique index, so they never collide with each other or with new votes.
            $table->unique(
                ['session_id', 'google_place_id', 'participant_id'],
                'unique_vote_per_participant_id'
            );
        });
    }

    public function down(): void
    {
        Schema::table('venue_votes', function (Blueprint $table) {
            $table->dropUnique('unique_vote_per_participant_id');
            $table->dropForeign(['participant_id']);
            $table->dropColumn('participant_id');

            $table->unique(
                ['session_id', 'google_place_id', 'participant_name'],
                'unique_vote_per_participant'
            );
        });
    }
};
