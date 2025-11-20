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
        Schema::create('registers_archives', function (Blueprint $table) {
            $table->id();
            $table->string('kode_outlet')->nullable();
            $table->foreignId('badanusaha_id');
            $table->foreignId('divisi_id');
            $table->string('nama_outlet');
            $table->text('alamat_outlet');
            $table->string('nama_pemilik_outlet');
            $table->string('nomer_tlp_outlet');
            $table->string('nomer_wakil_outlet')->nullable();
            $table->string('ktp_outlet');
            $table->string('distric');
            $table->foreignId('region_id');
            $table->foreignId('cluster_id');
            $table->string('poto_shop_sign');
            $table->string('poto_depan');
            $table->string('poto_kiri');
            $table->string('poto_kanan');
            $table->string('poto_ktp');
            $table->string('video');
            $table->string('oppo');
            $table->string('vivo');
            $table->string('realme');
            $table->string('samsung');
            $table->string('xiaomi');
            $table->string('fl');
            $table->string('latlong');
            $table->bigInteger('limit')->nullable();
            $table->enum('status', ['PENDING', 'CONFIRMED', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->string('created_by');
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('tm_id');
            $table->softDeletes();
            $table->timestamps();

            // Indexes from 2025_10_01_130000_add_composite_indexes_for_organizational_queries.php
            $table->index(['badanusaha_id', 'divisi_id'], 'registers_archives_bu_div_idx');
            $table->index(['badanusaha_id', 'divisi_id', 'region_id'], 'registers_archives_bu_div_reg_idx');
            $table->index(['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id'], 'registers_archives_bu_div_reg_clus_idx');
            $table->index(['status', 'deleted_at'], 'registers_archives_status_deleted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registers_archives');
    }
};
