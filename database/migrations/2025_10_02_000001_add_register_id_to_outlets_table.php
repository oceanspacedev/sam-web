<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->foreignId('register_id')
                ->nullable()
                ->after('id')
                ->constrained('registers')
                ->nullOnDelete();
        });

        DB::table('registers')
            ->whereNotNull('kode_outlet')
            ->orderBy('id')
            ->chunkById(100, function ($registers): void {
                foreach ($registers as $register) {
                    DB::table('outlets')
                        ->where('kode_outlet', $register->kode_outlet)
                        ->update(['register_id' => $register->id]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_id');
        });
    }
};
