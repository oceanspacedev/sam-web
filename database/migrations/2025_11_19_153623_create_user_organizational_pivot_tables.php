<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create pivot tables for many-to-many organizational assignments.
     * This allows users to be assigned to multiple badan usaha, divisi, regions, and clusters.
     */
    public function up(): void
    {
        // Create user_badan_usaha pivot table
        Schema::create('user_badan_usaha', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('badanusaha_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('badanusaha_id')->references('id')->on('badan_usahas')->onDelete('cascade');
            $table->unique(['user_id', 'badanusaha_id']);
        });

        // Create user_divisi pivot table
        Schema::create('user_divisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('divisi_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('divisi_id')->references('id')->on('divisions')->onDelete('cascade');
            $table->unique(['user_id', 'divisi_id']);
        });

        // Create user_regions pivot table
        Schema::create('user_regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('region_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');
            $table->unique(['user_id', 'region_id']);
        });

        // Create user_clusters pivot table
        Schema::create('user_clusters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cluster_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cluster_id')->references('id')->on('clusters')->onDelete('cascade');
            $table->unique(['user_id', 'cluster_id']);
        });

        // Data migration will be done separately via Artisan command for better error handling
        // $this->migrateExistingData();
    }

    /**
     * Migrate existing single-assignment data to pivot tables
     * NOTE: This is commented out and will be run separately
     */
    /*
    private function migrateExistingData(): void
    {
        // Migration logic preserved for reference
        // Will be converted to separate Artisan command
    }
    */

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_clusters');
        Schema::dropIfExists('user_regions');
        Schema::dropIfExists('user_divisi');
        Schema::dropIfExists('user_badan_usaha');
    }
};
