<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('outlets', 'is_member')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->dropColumn('is_member');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('outlets', 'is_member')) {
            Schema::table('outlets', function (Blueprint $table) {
                $table->enum('is_member', ['0', '1'])->default('1')->after('status_outlet');
            });
        }
    }
};
