<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->enum('scope_level', ['global', 'badanusaha', 'division', 'region', 'cluster'])->default('global');
                $table->foreignId('badanusaha_id')->nullable()->constrained('badan_usahas')->nullOnDelete();
                $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
                $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
                $table->foreignId('cluster_id')->nullable()->constrained('clusters')->nullOnDelete();
                $table->boolean('allow_register_visit')->default(false);
                $table->integer('default_register_radius')->default(100);
                $table->unsignedTinyInteger('plan_visit_min_days')->default(3);
                $table->timestamps();

                $table->index('scope_level');
                $table->index(['scope_level', 'badanusaha_id'], 'system_settings_scope_bu_idx');
                $table->index(['scope_level', 'division_id'], 'system_settings_scope_div_idx');
                $table->index(['scope_level', 'region_id'], 'system_settings_scope_reg_idx');
                $table->index(['scope_level', 'cluster_id'], 'system_settings_scope_clus_idx');
            });
        }

        if (! Schema::hasTable('division_settings') || ! Schema::hasTable('divisions')) {
            return;
        }

        $existingDivisionIds = DB::table('system_settings')
            ->where('scope_level', 'division')
            ->whereNotNull('division_id')
            ->pluck('division_id')
            ->all();

        $legacySettings = DB::table('division_settings')
            ->select(['division_id', 'allow_register_visit', 'default_register_radius'])
            ->when($existingDivisionIds !== [], fn ($query) => $query->whereNotIn('division_id', $existingDivisionIds))
            ->get();

        foreach ($legacySettings as $legacySetting) {
            $division = DB::table('divisions')
                ->where('id', $legacySetting->division_id)
                ->select(['id', 'badanusaha_id'])
                ->first();

            if (! $division) {
                continue;
            }

            DB::table('system_settings')->insert([
                'scope_level' => 'division',
                'badanusaha_id' => $division->badanusaha_id,
                'division_id' => $division->id,
                'region_id' => null,
                'cluster_id' => null,
                'allow_register_visit' => (bool) $legacySetting->allow_register_visit,
                'default_register_radius' => (int) ($legacySetting->default_register_radius ?? 100),
                'plan_visit_min_days' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
