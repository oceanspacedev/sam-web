<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // divisions
        Schema::table('divisions', function (Blueprint $table) {
            if (! $this->hasIndex('divisions', 'divisions_badanusaha_id_index')) {
                $table->index('badanusaha_id');
            }
            $this->addForeign('divisions', 'badanusaha_id', 'badan_usahas');
        });

        // regions
        Schema::table('regions', function (Blueprint $table) {
            if (! $this->hasIndex('regions', 'regions_badanusaha_id_index')) {
                $table->index('badanusaha_id');
            }
            if (! $this->hasIndex('regions', 'regions_divisi_id_index')) {
                $table->index('divisi_id');
            }
            $this->addForeign('regions', 'badanusaha_id', 'badan_usahas');
            $this->addForeign('regions', 'divisi_id', 'divisions');
        });

        // clusters
        Schema::table('clusters', function (Blueprint $table) {
            if (! $this->hasIndex('clusters', 'clusters_badanusaha_id_index')) {
                $table->index('badanusaha_id');
            }
            if (! $this->hasIndex('clusters', 'clusters_divisi_id_index')) {
                $table->index('divisi_id');
            }
            if (! $this->hasIndex('clusters', 'clusters_region_id_index')) {
                $table->index('region_id');
            }
            $this->addForeign('clusters', 'badanusaha_id', 'badan_usahas');
            $this->addForeign('clusters', 'divisi_id', 'divisions');
            $this->addForeign('clusters', 'region_id', 'regions');
        });

        // outlets
        Schema::table('outlets', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id'] as $col) {
                $indexName = 'outlets_'.$col.'_index';
                if (! $this->hasIndex('outlets', $indexName)) {
                    $table->index($col);
                }
            }
            $this->addForeign('outlets', 'badanusaha_id', 'badan_usahas');
            $this->addForeign('outlets', 'divisi_id', 'divisions');
            $this->addForeign('outlets', 'region_id', 'regions');
            $this->addForeign('outlets', 'cluster_id', 'clusters');
        });

        // users
        Schema::table('users', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'role_id', 'tm_id'] as $col) {
                $indexName = 'users_'.$col.'_index';
                if (! $this->hasIndex('users', $indexName)) {
                    $table->index($col);
                }
            }
            $this->addForeign('users', 'badanusaha_id', 'badan_usahas');
            $this->addForeign('users', 'divisi_id', 'divisions');
            $this->addForeign('users', 'region_id', 'regions');
            $this->addForeign('users', 'cluster_id', 'clusters');
            $this->addForeign('users', 'role_id', 'roles');
            // self-referencing foreign key for tm_id
            $this->addForeign('users', 'tm_id', 'users');
        });

        // noos
        Schema::table('noos', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'tm_id'] as $col) {
                $indexName = 'noos_'.$col.'_index';
                if (! $this->hasIndex('noos', $indexName)) {
                    $table->index($col);
                }
            }
            $this->addForeign('noos', 'badanusaha_id', 'badan_usahas');
            $this->addForeign('noos', 'divisi_id', 'divisions');
            $this->addForeign('noos', 'region_id', 'regions');
            $this->addForeign('noos', 'cluster_id', 'clusters');
            $this->addForeign('noos', 'tm_id', 'users');
        });

        // plan_visits
        Schema::table('plan_visits', function (Blueprint $table) {
            foreach (['user_id', 'outlet_id'] as $col) {
                $indexName = 'plan_visits_'.$col.'_index';
                if (! $this->hasIndex('plan_visits', $indexName)) {
                    $table->index($col);
                }
            }
            $this->addForeign('plan_visits', 'user_id', 'users');
            $this->addForeign('plan_visits', 'outlet_id', 'outlets');
        });
    }

    public function down(): void
    {
        // Drop foreign keys and indexes in reverse order
        Schema::table('plan_visits', function (Blueprint $table) {
            $this->dropForeignIfExists('plan_visits', 'user_id');
            $this->dropForeignIfExists('plan_visits', 'outlet_id');
            $this->dropIndexIfExists('plan_visits', 'plan_visits_user_id_index');
            $this->dropIndexIfExists('plan_visits', 'plan_visits_outlet_id_index');
        });

        Schema::table('noos', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'tm_id'] as $col) {
                $this->dropForeignIfExists('noos', $col);
                $this->dropIndexIfExists('noos', 'noos_'.$col.'_index');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'role_id', 'tm_id'] as $col) {
                $this->dropForeignIfExists('users', $col);
                $this->dropIndexIfExists('users', 'users_'.$col.'_index');
            }
        });

        Schema::table('outlets', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id'] as $col) {
                $this->dropForeignIfExists('outlets', $col);
                $this->dropIndexIfExists('outlets', 'outlets_'.$col.'_index');
            }
        });

        Schema::table('clusters', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id', 'region_id'] as $col) {
                $this->dropForeignIfExists('clusters', $col);
                $this->dropIndexIfExists('clusters', 'clusters_'.$col.'_index');
            }
        });

        Schema::table('regions', function (Blueprint $table) {
            foreach (['badanusaha_id', 'divisi_id'] as $col) {
                $this->dropForeignIfExists('regions', $col);
                $this->dropIndexIfExists('regions', 'regions_'.$col.'_index');
            }
        });

        Schema::table('divisions', function (Blueprint $table) {
            $this->dropForeignIfExists('divisions', 'badanusaha_id');
            $this->dropIndexIfExists('divisions', 'divisions_badanusaha_id_index');
        });
    }

    private function addForeign(string $table, string $column, string $references, string $refColumn = 'id'): void
    {
        // Compose a predictable constraint name
        $fkName = $table.'_'.$column.'_foreign';
        try {
            Schema::table($table, function (Blueprint $t) use ($column, $references, $refColumn, $fkName) {
                $t->foreign($column, $fkName)
                    ->references($refColumn)
                    ->on($references)
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        } catch (\Throwable $e) {
            // Ignore if the FK already exists or fails due to existing bad data
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $dbName = DB::getDatabaseName();
            $row = DB::selectOne(
                'SELECT 1 AS exists_flag FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$dbName, $table, $indexName]
            );

            return (bool) ($row->exists_flag ?? false);
        }

        if ($driver === 'sqlite') {
            $results = DB::select("PRAGMA index_list('{$table}')");
            foreach ($results as $row) {
                // SQLite returns objects with 'name' property
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        // Fallback: assume index does not exist
        return false;
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $fkName = $table.'_'.$column.'_foreign';
        try {
            Schema::table($table, function (Blueprint $t) use ($fkName) {
                $t->dropForeign($fkName);
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
