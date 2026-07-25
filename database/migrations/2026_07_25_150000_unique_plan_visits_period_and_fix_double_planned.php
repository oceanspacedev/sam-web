<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dedupePlanVisits();
        $this->fixDoublePlannedVisits();

        Schema::table('plan_visits', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'period_start'],
                'plan_visits_user_visitable_scope_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('plan_visits', function (Blueprint $table) {
            $table->dropUnique('plan_visits_user_visitable_scope_period_unique');
        });
    }

    private function dedupePlanVisits(): void
    {
        $groups = DB::table('plan_visits')
            ->select('user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'period_start')
            ->groupBy('user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'period_start')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('plan_visits')
                ->where('user_id', $group->user_id)
                ->where('visitable_type', $group->visitable_type)
                ->where('visitable_id', $group->visitable_id)
                ->where('schedule_scope', $group->schedule_scope)
                ->whereDate('period_start', $group->period_start)
                // Prefer active realized rows, then active unrealized, then trashed realized.
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderByRaw('CASE WHEN realized_visit_id IS NULL THEN 1 ELSE 0 END')
                ->orderByRaw('CASE WHEN realized_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->get(['id', 'realized_visit_id', 'realized_at', 'deleted_at']);

            $keeper = $rows->first();
            $duplicates = $rows->slice(1);

            foreach ($duplicates as $duplicate) {
                if ($duplicate->realized_visit_id && ! $keeper->realized_visit_id) {
                    DB::table('plan_visits')->where('id', $keeper->id)->update([
                        'realized_visit_id' => $duplicate->realized_visit_id,
                        'realized_at' => $duplicate->realized_at,
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                    $keeper->realized_visit_id = $duplicate->realized_visit_id;
                    $keeper->realized_at = $duplicate->realized_at;
                    $keeper->deleted_at = null;
                } elseif (
                    $duplicate->realized_visit_id
                    && $keeper->realized_visit_id
                    && (int) $duplicate->realized_visit_id !== (int) $keeper->realized_visit_id
                ) {
                    // Keeper already owns a realization; demote the duplicate's visit so it is not left as orphan PLANNED.
                    DB::table('visits')
                        ->where('id', $duplicate->realized_visit_id)
                        ->where('tipe_visit', 'PLANNED')
                        ->update([
                            'tipe_visit' => 'EXTRACALL',
                            'updated_at' => now(),
                        ]);
                }

                // Hard delete so unique index can be applied (soft-deleted rows still occupy unique keys).
                DB::table('plan_visits')->where('id', $duplicate->id)->delete();
            }
        }
    }

    private function fixDoublePlannedVisits(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $pairs = DB::select("
                SELECT user_id, visitable_type, visitable_id,
                    date(tanggal_visit, 'weekday 0', '-6 days') as week_start
                FROM visits
                WHERE tipe_visit = 'PLANNED' AND deleted_at IS NULL
                GROUP BY user_id, visitable_type, visitable_id, week_start
                HAVING COUNT(*) > 1
            ");
        } else {
            $pairs = DB::select("
                SELECT user_id, visitable_type, visitable_id,
                    DATE_SUB(DATE(tanggal_visit), INTERVAL WEEKDAY(tanggal_visit) DAY) as week_start
                FROM visits
                WHERE tipe_visit = 'PLANNED' AND deleted_at IS NULL
                GROUP BY user_id, visitable_type, visitable_id, week_start
                HAVING COUNT(*) > 1
            ");
        }

        foreach ($pairs as $pair) {
            $weekStart = $pair->week_start;
            $weekEnd = date('Y-m-d', strtotime($weekStart.' +6 days'));

            $visits = DB::table('visits')
                ->where('user_id', $pair->user_id)
                ->where('visitable_type', $pair->visitable_type)
                ->where('visitable_id', $pair->visitable_id)
                ->where('tipe_visit', 'PLANNED')
                ->whereNull('deleted_at')
                ->whereDate('tanggal_visit', '>=', $weekStart)
                ->whereDate('tanggal_visit', '<=', $weekEnd)
                ->orderBy('tanggal_visit')
                ->orderBy('id')
                ->get(['id', 'tanggal_visit', 'check_in_time']);

            if ($visits->count() < 2) {
                continue;
            }

            $keeper = $visits->first();

            foreach ($visits->slice(1) as $extra) {
                DB::table('visits')->where('id', $extra->id)->update([
                    'tipe_visit' => 'EXTRACALL',
                    'updated_at' => now(),
                ]);
            }

            $plan = DB::table('plan_visits')
                ->where('user_id', $pair->user_id)
                ->where('visitable_type', $pair->visitable_type)
                ->where('visitable_id', $pair->visitable_id)
                ->where('schedule_scope', 'weekly')
                ->whereDate('period_start', $weekStart)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if (! $plan) {
                continue;
            }

            DB::table('plan_visits')->where('id', $plan->id)->update([
                'realized_visit_id' => $keeper->id,
                'realized_at' => $keeper->check_in_time ?: ($keeper->tanggal_visit.' 00:00:00'),
                'updated_at' => now(),
            ]);
        }
    }
};
