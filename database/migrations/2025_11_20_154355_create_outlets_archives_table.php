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
        Schema::create('outlets_archives', function (Blueprint $table) {
            $table->id();

            // Original outlet ID (reference to source outlet)
            $table->foreignId('outlet_id')->nullable()->comment('Reference to original outlet');

            // All outlet fields (mirror of outlets table)
            $table->string('kode_outlet');
            $table->string('nama_outlet');
            $table->text('alamat_outlet');
            $table->string('nama_pemilik_outlet')->nullable();
            $table->string('nomer_tlp_outlet')->nullable();
            $table->foreignId('badanusaha_id');
            $table->foreignId('divisi_id');
            $table->foreignId('region_id');
            $table->foreignId('cluster_id');
            $table->string('distric');
            $table->string('poto_shop_sign')->nullable();
            $table->string('poto_depan')->nullable();
            $table->string('poto_kiri')->nullable();
            $table->string('poto_kanan')->nullable();
            $table->string('poto_ktp')->nullable();
            $table->string('video')->nullable();
            $table->integer('limit')->nullable();
            $table->integer('radius')->nullable();
            $table->string('latlong')->nullable();
            $table->enum('status_outlet', ['MAINTAIN', 'UNMAINTAIN', 'UNPRODUCTIVE']);

            // Archive metadata
            $table->string('archived_by')->nullable()->comment('User who archived this outlet');
            $table->timestamp('archived_at')->nullable()->comment('When this outlet was archived');
            $table->string('archive_reason')->nullable()->comment('Reason for archiving');

            // Original timestamps from outlets table
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('original_deleted_at')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('outlet_id');
            $table->index('kode_outlet');
            $table->index(['badanusaha_id', 'divisi_id'], 'outlets_archives_bu_div_idx');
            $table->index(['badanusaha_id', 'divisi_id', 'region_id'], 'outlets_archives_bu_div_reg_idx');
            $table->index(['status_outlet', 'archived_at'], 'outlets_archives_status_archived_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlets_archives');
    }
};
