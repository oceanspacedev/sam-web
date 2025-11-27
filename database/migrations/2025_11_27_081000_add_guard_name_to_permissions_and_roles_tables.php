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
        // Add guard_name to permissions table (required by Spatie Permission)
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('guard_name')->default('web')->after('name');
        });

        // Add guard_name to roles table (required by Spatie Permission)
        Schema::table('roles', function (Blueprint $table) {
            $table->string('guard_name')->default('web')->after('name');
        });

        // Update existing records to have guard_name = 'web'
        DB::table('permissions')->update(['guard_name' => 'web']);
        DB::table('roles')->update(['guard_name' => 'web']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('guard_name');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('guard_name');
        });
    }
};
