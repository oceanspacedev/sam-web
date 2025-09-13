<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOutletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
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
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('outlets');
    }
}
