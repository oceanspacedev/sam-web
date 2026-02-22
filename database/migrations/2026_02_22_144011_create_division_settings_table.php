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
        Schema::create('division_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->unique()->constrained('divisions')->cascadeOnDelete();
            $table->boolean('allow_register_visit')->default(false);
            $table->unsignedInteger('max_visit_per_day')->default(0);
            $table->unsignedInteger('default_register_radius')->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('division_settings');
    }
};
