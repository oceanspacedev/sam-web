<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

        $existingColumns = array_filter($columnsToDrop, fn (string $column) => Schema::hasColumn('users', $column));

        if (empty($existingColumns)) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        // Migrate existing data to pivot tables before dropping columns
        $this->migrateDataToPivotTables();

        // Drop foreign keys first
        foreach ($existingColumns as $column) {
            try {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                });
            } catch (\Exception $e) {
                // Ignore if FK doesn't exist
            }
        }

        Schema::table('users', function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Migrate existing organizational assignments to pivot tables
     */
    private function migrateDataToPivotTables(): void
    {
        // Get all users with their organizational assignments
        $users = DB::table('users')
            ->select('id', 'badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'cluster_id2')
            ->get();

        foreach ($users as $user) {
            $now = now();

            // Migrate badanusaha_id to user_badan_usaha pivot
            if ($user->badanusaha_id) {
                DB::table('user_badan_usaha')->insertOrIgnore([
                    'user_id' => $user->id,
                    'badanusaha_id' => $user->badanusaha_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Migrate divisi_id to user_divisi pivot
            if ($user->divisi_id) {
                DB::table('user_divisi')->insertOrIgnore([
                    'user_id' => $user->id,
                    'divisi_id' => $user->divisi_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Migrate region_id to user_regions pivot
            if ($user->region_id) {
                DB::table('user_regions')->insertOrIgnore([
                    'user_id' => $user->id,
                    'region_id' => $user->region_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Migrate cluster_id to user_clusters pivot
            if ($user->cluster_id) {
                DB::table('user_clusters')->insertOrIgnore([
                    'user_id' => $user->id,
                    'cluster_id' => $user->cluster_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Migrate cluster_id2 to user_clusters pivot (second cluster assignment)
            if ($user->cluster_id2) {
                DB::table('user_clusters')->insertOrIgnore([
                    'user_id' => $user->id,
                    'cluster_id' => $user->cluster_id2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
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
