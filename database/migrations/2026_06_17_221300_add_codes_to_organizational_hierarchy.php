<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCodeColumn('badan_usahas');
        $this->addCodeColumn('divisions');
        $this->addCodeColumn('regions');
        $this->addCodeColumn('clusters');

        $this->backfillCodes('badan_usahas');
        $this->backfillCodes('divisions');
        $this->backfillCodes('regions');
        $this->backfillCodes('clusters');

        $this->resolveScopedDuplicates('badan_usahas', [], 'code');
        $this->resolveScopedDuplicates('badan_usahas', [], 'name');
        $this->resolveScopedDuplicates('divisions', ['badanusaha_id'], 'code');
        $this->resolveScopedDuplicates('divisions', ['badanusaha_id'], 'name');
        $this->resolveScopedDuplicates('regions', ['divisi_id'], 'code');
        $this->resolveScopedDuplicates('regions', ['divisi_id'], 'name');
        $this->resolveScopedDuplicates('clusters', ['region_id'], 'code');
        $this->resolveScopedDuplicates('clusters', ['region_id'], 'name');

        $this->assertNoDuplicates('badan_usahas', ['code']);
        $this->assertNoDuplicates('badan_usahas', ['name']);
        $this->assertNoDuplicates('divisions', ['badanusaha_id', 'code']);
        $this->assertNoDuplicates('divisions', ['badanusaha_id', 'name']);
        $this->assertNoDuplicates('regions', ['divisi_id', 'code']);
        $this->assertNoDuplicates('regions', ['divisi_id', 'name']);
        $this->assertNoDuplicates('clusters', ['region_id', 'code']);
        $this->assertNoDuplicates('clusters', ['region_id', 'name']);

        Schema::table('clusters', function (Blueprint $table): void {
            $table->dropUnique('clusters_name_unique');
        });

        Schema::table('badan_usahas', function (Blueprint $table): void {
            $table->unique('code', 'org_bu_code_unique');
            $table->unique('name', 'org_bu_name_unique');
        });

        Schema::table('divisions', function (Blueprint $table): void {
            $table->unique(['badanusaha_id', 'code'], 'org_div_parent_code_unique');
            $table->unique(['badanusaha_id', 'name'], 'org_div_parent_name_unique');
        });

        Schema::table('regions', function (Blueprint $table): void {
            $table->unique(['divisi_id', 'code'], 'org_reg_parent_code_unique');
            $table->unique(['divisi_id', 'name'], 'org_reg_parent_name_unique');
        });

        Schema::table('clusters', function (Blueprint $table): void {
            $table->unique(['region_id', 'code'], 'org_clu_parent_code_unique');
            $table->unique(['region_id', 'name'], 'org_clu_parent_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clusters', function (Blueprint $table): void {
            $table->dropUnique('org_clu_parent_name_unique');
            $table->dropUnique('org_clu_parent_code_unique');
        });

        Schema::table('regions', function (Blueprint $table): void {
            $table->dropUnique('org_reg_parent_name_unique');
            $table->dropUnique('org_reg_parent_code_unique');
        });

        Schema::table('divisions', function (Blueprint $table): void {
            $table->dropUnique('org_div_parent_name_unique');
            $table->dropUnique('org_div_parent_code_unique');
        });

        Schema::table('badan_usahas', function (Blueprint $table): void {
            $table->dropUnique('org_bu_name_unique');
            $table->dropUnique('org_bu_code_unique');
        });

        Schema::table('clusters', function (Blueprint $table): void {
            $table->unique('name', 'clusters_name_unique');
        });

        $this->dropCodeColumn('clusters');
        $this->dropCodeColumn('regions');
        $this->dropCodeColumn('divisions');
        $this->dropCodeColumn('badan_usahas');
    }

    private function addCodeColumn(string $tableName): void
    {
        if (Schema::hasColumn($tableName, 'code')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('code')->nullable()->after('id');
        });
    }

    private function dropCodeColumn(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'code')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('code');
        });
    }

    private function backfillCodes(string $tableName): void
    {
        DB::table($tableName)
            ->select(['id', 'name'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row) use ($tableName): void {
                DB::table($tableName)
                    ->where('id', $row->id)
                    ->update(['code' => $this->formatCode((string) $row->name)]);
            });
    }

    /**
     * Keep the active (or oldest) row in each duplicate group and suffix
     * conflicting rows so parent-scoped unique indexes can be added safely.
     */
    private function resolveScopedDuplicates(string $tableName, array $parentColumns, string $valueColumn): void
    {
        $groupColumns = array_merge($parentColumns, [$valueColumn]);

        $duplicateGroups = DB::table($tableName)
            ->select($groupColumns)
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy($groupColumns)
            ->having('duplicate_count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $query = DB::table($tableName);

            foreach ($parentColumns as $column) {
                $query->where($column, $group->{$column});
            }

            $rows = $query
                ->where($valueColumn, $group->{$valueColumn})
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('id')
                ->get(['id', $valueColumn]);

            foreach ($rows->skip(1) as $row) {
                DB::table($tableName)
                    ->where('id', $row->id)
                    ->update([
                        $valueColumn => $row->{$valueColumn}.'_'.$row->id,
                    ]);
            }
        }
    }

    private function assertNoDuplicates(string $tableName, array $columns): void
    {
        $duplicates = DB::table($tableName)
            ->select($columns)
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy($columns)
            ->having('duplicate_count', '>', 1)
            ->first();

        if (! $duplicates) {
            return;
        }

        $scope = collect($columns)
            ->map(fn (string $column): string => "{$column}={$duplicates->{$column}}")
            ->implode(', ');

        throw new RuntimeException("Cannot add organizational unique index on {$tableName}: duplicate {$scope} already exists.");
    }

    private function formatCode(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', '_', $value) ?? $value;

        return Str::upper($value);
    }
};
