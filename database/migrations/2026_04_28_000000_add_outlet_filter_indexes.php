<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('outlets', ['status_outlet', 'deleted_at'], 'outlets_status_deleted_idx');
        $this->addIndex('outlets', ['kode_outlet'], 'outlets_kode_outlet_idx');
        $this->addIndex('outlets', ['nama_outlet'], 'outlets_nama_outlet_idx');
    }

    public function down(): void
    {
        $this->dropIndex('outlets', 'outlets_nama_outlet_idx');
        $this->dropIndex('outlets', 'outlets_kode_outlet_idx');
        $this->dropIndex('outlets', 'outlets_status_deleted_idx');
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
