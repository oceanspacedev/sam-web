<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unify location and data reset tracking into single fields.
     */
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            // Add unified fields
            $table->timestamp('last_reset_at')->nullable()->after('radius');
            $table->unsignedInteger('reset_count_yearly')->default(0)->after('last_reset_at');
        });

        // Migrate data: use the most recent reset date from either location or data
        DB::statement("
            UPDATE outlets 
            SET last_reset_at = GREATEST(
                COALESCE(last_location_reset_at, '1900-01-01'),
                COALESCE(last_data_reset_at, '1900-01-01')
            ),
            reset_count_yearly = COALESCE(location_reset_count_yearly, 0) + COALESCE(data_reset_count_yearly, 0)
            WHERE last_location_reset_at IS NOT NULL OR last_data_reset_at IS NOT NULL
        ");

        // Set null for outlets that never had any reset
        DB::statement("
            UPDATE outlets 
            SET last_reset_at = NULL
            WHERE last_reset_at = '1900-01-01'
        ");

        // Drop old columns
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn([
                'last_location_reset_at',
                'location_reset_count_yearly',
                'last_data_reset_at',
                'data_reset_count_yearly',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            // Restore old columns
            $table->timestamp('last_location_reset_at')->nullable();
            $table->unsignedInteger('location_reset_count_yearly')->default(0);
            $table->timestamp('last_data_reset_at')->nullable();
            $table->unsignedInteger('data_reset_count_yearly')->default(0);
        });

        // Copy unified data to location fields (best effort restore)
        DB::statement('
            UPDATE outlets 
            SET last_location_reset_at = last_reset_at,
                location_reset_count_yearly = reset_count_yearly
            WHERE last_reset_at IS NOT NULL
        ');

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['last_reset_at', 'reset_count_yearly']);
        });
    }
};
