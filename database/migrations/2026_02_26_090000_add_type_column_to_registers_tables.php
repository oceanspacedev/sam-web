<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addTypeColumnIfMissing('registers');
        $this->backfillTypeFromLegacyData('registers');

        if (Schema::hasTable('registers_archives')) {
            $this->addTypeColumnIfMissing('registers_archives');
            $this->backfillTypeFromLegacyData('registers_archives');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTypeColumnIfExists('registers');

        if (Schema::hasTable('registers_archives')) {
            $this->dropTypeColumnIfExists('registers_archives');
        }
    }

    private function addTypeColumnIfMissing(string $tableName): void
    {
        if (Schema::hasColumn($tableName, 'type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('type', 10)->default('NOO')->after('status');
        });
    }

    private function dropTypeColumnIfExists(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }

    private function backfillTypeFromLegacyData(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'type')) {
            return;
        }

        // Normalize legacy / invalid values to NOO first.
        DB::table($tableName)
            ->where(function ($query): void {
                $query->whereNull('type')
                    ->orWhere('type', '')
                    ->orWhereRaw("UPPER(type) NOT IN ('LEAD', 'NOO')");
            })
            ->update(['type' => 'NOO']);

        // Normalize casing.
        DB::table($tableName)
            ->whereRaw("UPPER(type) = 'LEAD'")
            ->update(['type' => 'LEAD']);
        DB::table($tableName)
            ->whereRaw("UPPER(type) = 'NOO'")
            ->update(['type' => 'NOO']);

        // Legacy mapping: historically LEAD was stored in keterangan.
        DB::table($tableName)
            ->whereRaw("UPPER(COALESCE(keterangan, '')) = 'LEAD'")
            ->update(['type' => 'LEAD']);
    }
};
