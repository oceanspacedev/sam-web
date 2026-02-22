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
        Schema::create('register_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained('registers')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('division_register_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->unique(['register_id', 'field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('register_field_values');
    }
};
