<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->timestamp('last_data_reset_at')->nullable()->after('location_reset_count_yearly');
            $table->unsignedTinyInteger('data_reset_count_yearly')->default(0)->after('last_data_reset_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['last_data_reset_at', 'data_reset_count_yearly']);
        });
    }
};
