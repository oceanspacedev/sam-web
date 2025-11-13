<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var array<int, string> $tables */
        $tables = [
            'telescope_entries_tags',
            'telescope_entries',
            'telescope_monitoring',
            'pulse_aggregates',
            'pulse_entries',
            'pulse_values',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The original schemas for these tables are unknown, so no action is taken.
     */
    public function down(): void {}
};
