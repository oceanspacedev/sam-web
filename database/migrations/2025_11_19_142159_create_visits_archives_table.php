<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visits_archives', function (Blueprint $table) {
            $table->id();
            $table->timestamp('tanggal_visit');
            $table->foreignId('user_id'); // No constraint for archives
            $table->foreignId('outlet_id'); // No constraint for archives
            $table->string('tipe_visit');
            $table->string('latlong_in')->nullable();
            $table->string('latlong_out')->nullable();
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->text('laporan_visit')->nullable();
            $table->enum('transaksi', ['YES', 'NO'])->nullable();
            $table->integer('durasi_visit')->nullable();
            $table->text('picture_visit_in')->nullable();
            $table->text('picture_visit_out')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes from original table
            $table->index(['user_id', 'tanggal_visit'], 'visits_archives_user_date_idx');
            $table->index(['outlet_id', 'tanggal_visit'], 'visits_archives_outlet_date_idx');
            $table->index(['deleted_at', 'tanggal_visit'], 'visits_archives_deleted_date_idx');
            $table->index(['updated_at', 'deleted_at'], 'visits_archives_updated_deleted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits_archives');
    }
};
