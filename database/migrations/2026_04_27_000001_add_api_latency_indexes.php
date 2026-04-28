<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('registers', ['created_by_id', 'deleted_at', 'created_at'], 'registers_created_deleted_created_idx');
        $this->addIndex('registers', ['tm_id', 'deleted_at', 'created_at'], 'registers_tm_deleted_created_idx');
        $this->addIndex('registers', ['type', 'status', 'deleted_at'], 'registers_type_status_deleted_idx');

        $this->addIndex('visits', ['user_id', 'tanggal_visit', 'check_out_time'], 'visits_user_day_checkout_idx');
        $this->addIndex('visits', ['user_id', 'visitable_type', 'visitable_id', 'tanggal_visit'], 'visits_user_target_day_idx');

        $this->addIndex('plan_visits', ['user_id', 'realized_at', 'schedule_scope', 'period_start', 'period_end'], 'plan_visits_user_realized_period_idx');
    }

    public function down(): void
    {
        $this->dropIndex('plan_visits', 'plan_visits_user_realized_period_idx');
        $this->dropIndex('visits', 'visits_user_target_day_idx');
        $this->dropIndex('visits', 'visits_user_day_checkout_idx');
        $this->dropIndex('registers', 'registers_type_status_deleted_idx');
        $this->dropIndex('registers', 'registers_tm_deleted_created_idx');
        $this->dropIndex('registers', 'registers_created_deleted_created_idx');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndex(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndex(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list('{$table}')");

                foreach ($indexes as $index) {
                    if (($index->name ?? null) === $indexName) {
                        return true;
                    }
                }

                return false;
            }

            $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($result) > 0;
        } catch (Throwable) {
            return false;
        }
    }
};
