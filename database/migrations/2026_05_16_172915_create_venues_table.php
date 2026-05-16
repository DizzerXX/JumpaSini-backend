<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->enum('type', ['mall', 'restaurant', 'cafe', 'park', 'beach', 'waterfall', 'river']);
            $table->boolean('is_halal')->nullable();
            $table->boolean('is_halal_verified')->default(false);
            $table->string('google_place_id')->nullable()->unique();
            $table->decimal('google_rating', 2, 1)->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('photo_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
