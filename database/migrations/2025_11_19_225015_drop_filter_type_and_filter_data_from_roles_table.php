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
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['filter_type', 'filter_data']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('filter_type', ['badanusaha', 'divisi', 'region', 'cluster', 'all'])->default('all')->after('can_access_web');
            $table->json('filter_data')->nullable()->after('filter_type');
        });
    }
};
