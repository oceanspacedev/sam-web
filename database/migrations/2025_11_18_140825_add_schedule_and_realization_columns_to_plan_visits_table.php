<?php

use Carbon\Carbon;
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
        Schema::table('plan_visits', function (Blueprint $table) {
            $table->string('schedule_scope')->default('daily')->after('tanggal_visit');
            $table->date('period_start')->nullable()->after('schedule_scope');
            $table->date('period_end')->nullable()->after('period_start');
            $table->unsignedTinyInteger('schedule_week')->nullable()->after('period_end');
            $table->unsignedSmallInteger('schedule_year')->nullable()->after('schedule_week');
            $table->timestamp('realized_at')->nullable()->after('schedule_year');
            $table->foreignId('realized_visit_id')
                ->nullable()
                ->after('realized_at')
                ->constrained('visits')
                ->nullOnDelete();

            $table->index(
                ['user_id', 'outlet_id', 'schedule_scope', 'realized_at'],
                'plan_visits_scope_realized_index'
            );

            $table->index(
                ['user_id', 'outlet_id', 'schedule_week', 'schedule_year'],
                'plan_visits_schedule_week_index'
            );
        });

        DB::table('plan_visits')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($plan): void {
                if (! $plan->tanggal_visit) {
                    return;
                }

                $date = Carbon::parse($plan->tanggal_visit)->startOfDay();

                DB::table('plan_visits')
                    ->where('id', $plan->id)
                    ->update([
                        'schedule_scope' => 'daily',
                        'period_start' => $date->toDateString(),
                        'period_end' => $date->toDateString(),
                        'schedule_week' => $date->weekOfYear,
                        'schedule_year' => $date->year,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_visits', function (Blueprint $table) {
            $table->dropIndex('plan_visits_scope_realized_index');
            $table->dropIndex('plan_visits_schedule_week_index');
            $table->dropConstrainedForeignId('realized_visit_id');
            $table->dropColumn([
                'schedule_scope',
                'period_start',
                'period_end',
                'schedule_week',
                'schedule_year',
                'realized_at',
            ]);
        });
    }
};
