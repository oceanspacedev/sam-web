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
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tm_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tm_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('tm_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->whereNull('tm_id')->update([
            'tm_id' => DB::raw('id'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tm_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tm_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('tm_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
