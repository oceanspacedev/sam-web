<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make organizational hierarchy fields nullable to support dynamic roles.
     * Roles with 'all' scope don't need organizational assignments.
     * Roles with 'divisi' scope don't need region/cluster assignments.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Make all organizational fields nullable
            $table->unsignedBigInteger('badanusaha_id')->nullable()->change();
            $table->unsignedBigInteger('divisi_id')->nullable()->change();
            $table->unsignedBigInteger('region_id')->nullable()->change();
            $table->unsignedBigInteger('cluster_id')->nullable()->change();
            $table->unsignedBigInteger('cluster_id2')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert to NOT NULL (be careful with existing null values)
            $table->unsignedBigInteger('badanusaha_id')->nullable(false)->change();
            $table->unsignedBigInteger('divisi_id')->nullable(false)->change();
            $table->unsignedBigInteger('region_id')->nullable(false)->change();
            $table->unsignedBigInteger('cluster_id')->nullable(false)->change();
            $table->unsignedBigInteger('cluster_id2')->nullable(false)->change();
        });
    }
};
