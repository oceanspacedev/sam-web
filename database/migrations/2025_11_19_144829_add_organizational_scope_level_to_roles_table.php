<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('organizational_scope_level', ['all', 'badanusaha', 'divisi', 'region', 'cluster'])
                ->default('cluster')
                ->after('filter_data')
                ->comment('Defines the organizational hierarchy level for data access filtering');
        });

        // Migrate existing roles based on naming convention
        $this->migrateExistingRoles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('organizational_scope_level');
        });
    }

    /**
     * Migrate existing roles to appropriate scope levels
     */
    private function migrateExistingRoles(): void
    {
        // Map role names to their organizational scope
        $roleMappings = [
            // Full access roles
            'SUPER ADMIN' => 'all',

            // Division level (requires divisi + region parameters)
            'COO' => 'divisi',
            'ASM' => 'divisi',
            'RKAM' => 'divisi',
            'CSO' => 'divisi',
            'CSO FAST EV' => 'divisi',

            // Cluster level (filtered by badanusaha, divisi, region, cluster IDs)
            'ASC' => 'cluster',
            'DSF/DM' => 'cluster',
            'KAM' => 'cluster',  // Filtered by badanusaha, divisi, region
        ];

        foreach ($roleMappings as $roleName => $scopeLevel) {
            DB::table('roles')
                ->where('name', $roleName)
                ->update(['organizational_scope_level' => $scopeLevel]);
        }

        // For any unmapped roles, check their filter_type
        DB::table('roles')
            ->whereNotIn('name', array_keys($roleMappings))
            ->get()
            ->each(function ($role) {
                $scopeLevel = match ($role->filter_type) {
                    'all' => 'all',
                    'badanusaha' => 'badanusaha',
                    'divisi' => 'divisi',
                    'region' => 'region',
                    'cluster' => 'cluster',
                    default => 'cluster',
                };

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['organizational_scope_level' => $scopeLevel]);
            });
    }
};
