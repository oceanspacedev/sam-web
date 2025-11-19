<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_visits_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id'); // No constraint
            $table->foreignId('outlet_id'); // No constraint
            $table->timestamp('tanggal_visit');

            // Columns from 2025_11_18_140825_add_schedule_and_realization_columns_to_plan_visits_table
            $table->string('schedule_scope')->default('daily');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedTinyInteger('schedule_week')->nullable();
            $table->unsignedSmallInteger('schedule_year')->nullable();
            $table->timestamp('realized_at')->nullable();
            $table->foreignId('realized_visit_id')->nullable(); // No constraint

            $table->softDeletes(); // Added in 2025_10_01_120000
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'tanggal_visit'], 'plan_visits_archives_user_date_idx');
            $table->index(['outlet_id', 'tanggal_visit'], 'plan_visits_archives_outlet_date_idx');
            $table->index(['deleted_at', 'tanggal_visit'], 'plan_visits_archives_deleted_date_idx');
            $table->index(['updated_at', 'deleted_at'], 'plan_visits_archives_updated_deleted_idx');

            $table->index(
                ['user_id', 'outlet_id', 'schedule_scope', 'realized_at'],
                'plan_visits_archives_scope_realized_index'
            );

            $table->index(
                ['user_id', 'outlet_id', 'schedule_week', 'schedule_year'],
                'plan_visits_archives_schedule_week_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_visits_archives');
    }
};
