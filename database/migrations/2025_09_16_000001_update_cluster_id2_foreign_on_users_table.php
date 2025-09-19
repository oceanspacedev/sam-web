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
        // Skip for SQLite as it doesn't support dropping foreign keys
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cluster_id2']);
            $table->foreign('cluster_id2')
                ->references('id')
                ->on('clusters')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip for SQLite as it doesn't support dropping foreign keys
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cluster_id2']);
            $table->foreign('cluster_id2')
                ->references('id')
                ->on('clusters');
        });
    }
};
