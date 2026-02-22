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
        Schema::create('division_register_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->string('name'); // slug
            $table->string('label');
            $table->string('type'); // text, number, select, checkbox, date, file
            $table->json('options')->nullable(); // for select: ["opt1","opt2"]
            $table->boolean('is_required')->default(false);
            $table->string('applies_to')->default('both'); // lead, noo, both
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['division_id', 'applies_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('division_register_fields');
    }
};
