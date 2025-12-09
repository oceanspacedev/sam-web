<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->timestamp('last_location_reset_at')->nullable()->after('latlong');
            $table->unsignedTinyInteger('location_reset_count_yearly')->default(0)->after('last_location_reset_at');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['last_location_reset_at', 'location_reset_count_yearly']);
        });
    }
};
