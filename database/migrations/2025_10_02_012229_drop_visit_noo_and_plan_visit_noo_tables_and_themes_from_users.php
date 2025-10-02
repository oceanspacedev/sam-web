<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('visit_noo');
        Schema::dropIfExists('plan_visit_noo');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theme', 'theme_color']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->nullable()->default('default');
            $table->string('theme_color')->nullable();
        });

        Schema::create('visit_noo', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('plan_visit_noo', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
