<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVisitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->timestamp('tanggal_visit');
            $table->foreignId('user_id');
            $table->foreignId('outlet_id');
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
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('visits');
    }
}
