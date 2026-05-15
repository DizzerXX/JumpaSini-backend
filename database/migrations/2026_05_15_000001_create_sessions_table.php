<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('short_code', 8)->unique();
            $table->enum('transport_mode', ['car', 'public_transport']);
            $table->enum('venue_type', ['mall', 'restaurant', 'cafe', 'park', 'beach', 'waterfall', 'river']);
            $table->boolean('halal_filter')->default(true);
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
