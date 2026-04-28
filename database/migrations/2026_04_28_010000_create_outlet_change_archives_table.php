<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_change_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('kode_outlet')->nullable();
            $table->string('action', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->json('old_values');
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('request_meta')->nullable();
            $table->foreignId('restored_from_id')->nullable()->constrained('outlet_change_archives')->nullOnDelete();
            $table->foreignId('restored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['outlet_id', 'created_at'], 'outlet_change_archives_outlet_created_idx');
            $table->index(['action', 'created_at'], 'outlet_change_archives_action_created_idx');
            $table->index('kode_outlet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_change_archives');
    }
};
