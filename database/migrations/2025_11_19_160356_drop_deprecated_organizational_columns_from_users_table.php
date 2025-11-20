<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Drop deprecated single foreign key columns.
     * Data has been migrated to pivot tables.
     */
    public function up(): void
    {
        // Skip this migration for SQLite (used in testing)
        // SQLite has issues with dropping columns that have foreign keys
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $columnsToDrop = [
            'badanusaha_id',
            'divisi_id',
            'region_id',
            'cluster_id',
            'cluster_id2',
        ];

        Schema::table('users', function (Blueprint $table) use ($columnsToDrop) {
            // Drop foreign keys first
            foreach ($columnsToDrop as $column) {
                try {
                    $table->dropForeign("users_{$column}_foreign");
                } catch (\Exception $e) {
                    // Foreign key doesn't exist, continue
                }
            }
        });

        Schema::table('users', function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore columns (but data will be lost!)
            $table->unsignedBigInteger('badanusaha_id')->nullable();
            $table->unsignedBigInteger('divisi_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->unsignedBigInteger('cluster_id2')->nullable();

            // Note: Foreign keys are NOT restored in down()
            // You would need to manually restore them if rolling back
        });
    }
};
