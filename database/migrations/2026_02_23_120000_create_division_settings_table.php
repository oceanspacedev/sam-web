<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('division_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->onDelete('cascade');
            $table->boolean('allow_register_visit')->default(false);
            $table->integer('default_register_radius')->default(100);
            $table->timestamps();

            $table->unique('division_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_settings');
    }
};
