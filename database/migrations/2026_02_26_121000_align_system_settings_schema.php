<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'plan_visit_min_days')) {
                $table->unsignedTinyInteger('plan_visit_min_days')->default(3)->after('default_register_radius');
            }

            if (Schema::hasColumn('system_settings', 'allow_register_plan_visit')) {
                $table->dropColumn('allow_register_plan_visit');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'allow_register_plan_visit')) {
                $table->boolean('allow_register_plan_visit')->default(false)->after('allow_register_visit');
            }

            if (Schema::hasColumn('system_settings', 'plan_visit_min_days')) {
                $table->dropColumn('plan_visit_min_days');
            }
        });
    }
};
