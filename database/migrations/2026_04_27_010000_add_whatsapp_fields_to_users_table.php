<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasWhatsappNumber = Schema::hasColumn('users', 'whatsapp_number');

        if (! $hasWhatsappNumber) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('whatsapp_number', 20)->nullable()->after('profile_photo_path');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->unique('whatsapp_number');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'whatsapp_verified_at')) {
                $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'whatsapp_verified_at')) {
                $table->dropColumn('whatsapp_verified_at');
            }
        });

        if (Schema::hasColumn('users', 'whatsapp_number')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique('users_whatsapp_number_unique');
                });
            } catch (\Throwable) {
                //
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('whatsapp_number');
            });
        }
    }
};
