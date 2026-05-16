<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->decimal('midpoint_lat', 10, 8)->nullable()->after('halal_filter');
            $table->decimal('midpoint_lng', 11, 8)->nullable()->after('midpoint_lat');
            $table->timestamp('midpoint_calculated_at')->nullable()->after('midpoint_lng');
            $table->boolean('midpoint_is_fair')->nullable()->after('midpoint_calculated_at');
            $table->json('midpoint_travel_times')->nullable()->after('midpoint_is_fair');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn([
                'midpoint_lat',
                'midpoint_lng',
                'midpoint_calculated_at',
                'midpoint_is_fair',
                'midpoint_travel_times',
            ]);
        });
    }
};
