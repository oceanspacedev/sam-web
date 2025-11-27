<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's check the structure of role_has_permissions table
        $rolePermissions = DB::table('role_has_permissions')->get();

        echo "Migrating {$rolePermissions->count()} role-permission relationships...\n";

        $migratedCount = 0;

        // Migrate each role-permission relationship to model_has_permissions
        foreach ($rolePermissions as $rp) {
            // Check if this permission already exists in model_has_permissions
            $exists = DB::table('model_has_permissions')
                ->where('permission_id', $rp->permission_id)
                ->where('model_type', 'App\\Models\\Role')
                ->where('model_id', $rp->role_id)
                ->exists();

            if (! $exists) {
                DB::table('model_has_permissions')->insert([
                    'permission_id' => $rp->permission_id,
                    'model_type' => 'App\\Models\\Role',
                    'model_id' => $rp->role_id,
                ]);
                $migratedCount++;
            }
        }

        echo "Successfully migrated {$migratedCount} role-permission relationships.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated data from model_has_permissions
        DB::table('model_has_permissions')
            ->where('model_type', 'App\\Models\\Role')
            ->delete();

        echo "Rolled back role-permission migrations.\n";
    }
};
