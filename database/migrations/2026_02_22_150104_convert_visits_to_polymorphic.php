<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- visits table ---
        // Add columns if they don't exist (for idempotency)
        if (! Schema::hasColumn('visits', 'visitable_type')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->string('visitable_type')->nullable()->after('user_id');
            });
        }
        if (! Schema::hasColumn('visits', 'visitable_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
            });
        }

        // Migrate existing data only if outlet_id still exists
        if (Schema::hasColumn('visits', 'outlet_id')) {
            // Only update rows where visitable_type is null (not yet migrated)
            DB::table('visits')
                ->whereNull('visitable_type')
                ->whereNotNull('outlet_id')
                ->update([
                    'visitable_type' => 'App\\Models\\Outlet',
                    'visitable_id' => DB::raw('outlet_id'),
                ]);
        }

        // Drop outlet_id and add new indexes if not yet done
        if (Schema::hasColumn('visits', 'outlet_id')) {
            Schema::table('visits', function (Blueprint $table) {
                // Make columns non-nullable
                $table->string('visitable_type')->nullable(false)->change();
                $table->unsignedBigInteger('visitable_id')->nullable(false)->change();

                // Drop old index if exists
                if ($this->indexExists('visits', 'visits_outlet_date_idx')) {
                    $table->dropIndex('visits_outlet_date_idx');
                }

                $table->dropColumn('outlet_id');
            });
        }

        // Add new indexes if they don't exist
        if (! $this->indexExists('visits', 'visits_visitable_idx')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->index(['visitable_type', 'visitable_id'], 'visits_visitable_idx');
            });
        }
        if (! $this->indexExists('visits', 'visits_visitable_date_idx')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->index(['visitable_type', 'visitable_id', 'tanggal_visit'], 'visits_visitable_date_idx');
            });
        }

        // --- plan_visits table ---
        // Add columns if they don't exist
        if (! Schema::hasColumn('plan_visits', 'visitable_type')) {
            Schema::table('plan_visits', function (Blueprint $table) {
                $table->string('visitable_type')->nullable()->after('user_id');
            });
        }
        if (! Schema::hasColumn('plan_visits', 'visitable_id')) {
            Schema::table('plan_visits', function (Blueprint $table) {
                $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
            });
        }

        // Migrate existing data only if outlet_id still exists
        if (Schema::hasColumn('plan_visits', 'outlet_id')) {
            // Only update rows where visitable_type is null (not yet migrated)
            DB::table('plan_visits')
                ->whereNull('visitable_type')
                ->whereNotNull('outlet_id')
                ->update([
                    'visitable_type' => 'App\\Models\\Outlet',
                    'visitable_id' => DB::raw('outlet_id'),
                ]);
        }

        // Drop foreign key first (must be done before dropping indexes)
        if ($this->foreignKeyExists('plan_visits', 'outlet_id')) {
            Schema::table('plan_visits', function (Blueprint $table) {
                $table->dropForeign(['outlet_id']);
            });
        }

        // Now drop old indexes (safe after FK is dropped)
        Schema::table('plan_visits', function (Blueprint $table) {
            $indexesToDrop = [
                'plan_visits_outlet_date_idx',
                'plan_visits_outlet_id_index',
                'plan_visits_user_outlet_scope_realized_idx',
                'plan_visits_user_outlet_week_year_idx',
            ];

            foreach ($indexesToDrop as $index) {
                if ($this->indexExists('plan_visits', $index)) {
                    $table->dropIndex($index);
                }
            }
        });

        // Drop outlet_id and finalize columns
        if (Schema::hasColumn('plan_visits', 'outlet_id')) {
            Schema::table('plan_visits', function (Blueprint $table) {
                $table->string('visitable_type')->nullable(false)->change();
                $table->unsignedBigInteger('visitable_id')->nullable(false)->change();
                $table->dropColumn('outlet_id');
            });
        }

        // Add new indexes if they don't exist
        if (! $this->indexExists('plan_visits', 'plan_visits_visitable_idx')) {
            Schema::table('plan_visits', function (Blueprint $table) {
                $table->index(['visitable_type', 'visitable_id'], 'plan_visits_visitable_idx');
            });
        }
        if (! $this->indexExists('plan_visits', 'plan_visits_user_visitable_scope_realized_idx')) {
            Schema::table('plan_visits', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'realized_at'],
                    'plan_visits_user_visitable_scope_realized_idx'
                );
            });
        }

        // --- archive tables (if they exist) ---
        if (Schema::hasTable('visits_archives')) {
            // Add columns if they don't exist
            if (! Schema::hasColumn('visits_archives', 'visitable_type')) {
                Schema::table('visits_archives', function (Blueprint $table) {
                    $table->string('visitable_type')->nullable()->after('user_id');
                });
            }
            if (! Schema::hasColumn('visits_archives', 'visitable_id')) {
                Schema::table('visits_archives', function (Blueprint $table) {
                    $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
                });
            }

            // Migrate data if outlet_id still exists
            if (Schema::hasColumn('visits_archives', 'outlet_id')) {
                DB::table('visits_archives')
                    ->whereNull('visitable_type')
                    ->whereNotNull('outlet_id')
                    ->update([
                        'visitable_type' => 'App\\Models\\Outlet',
                        'visitable_id' => DB::raw('outlet_id'),
                    ]);

                Schema::table('visits_archives', function (Blueprint $table) {
                    if ($this->indexExists('visits_archives', 'visits_archives_outlet_date_idx')) {
                        $table->dropIndex('visits_archives_outlet_date_idx');
                    }
                    $table->dropColumn('outlet_id');
                });
            }

            if (! $this->indexExists('visits_archives', 'visits_archives_visitable_date_idx')) {
                Schema::table('visits_archives', function (Blueprint $table) {
                    $table->index(['visitable_type', 'visitable_id', 'tanggal_visit'], 'visits_archives_visitable_date_idx');
                });
            }
        }

        if (Schema::hasTable('plan_visits_archives')) {
            // Add columns if they don't exist
            if (! Schema::hasColumn('plan_visits_archives', 'visitable_type')) {
                Schema::table('plan_visits_archives', function (Blueprint $table) {
                    $table->string('visitable_type')->nullable()->after('user_id');
                });
            }
            if (! Schema::hasColumn('plan_visits_archives', 'visitable_id')) {
                Schema::table('plan_visits_archives', function (Blueprint $table) {
                    $table->unsignedBigInteger('visitable_id')->nullable()->after('visitable_type');
                });
            }

            // Migrate data if outlet_id still exists
            if (Schema::hasColumn('plan_visits_archives', 'outlet_id')) {
                DB::table('plan_visits_archives')
                    ->whereNull('visitable_type')
                    ->whereNotNull('outlet_id')
                    ->update([
                        'visitable_type' => 'App\\Models\\Outlet',
                        'visitable_id' => DB::raw('outlet_id'),
                    ]);

                Schema::table('plan_visits_archives', function (Blueprint $table) {
                    foreach ([
                        'plan_visits_archives_outlet_date_idx',
                        'plan_visits_archives_user_outlet_scope_realized_idx',
                        'plan_visits_archives_user_outlet_week_year_idx',
                    ] as $index) {
                        if ($this->indexExists('plan_visits_archives', $index)) {
                            $table->dropIndex($index);
                        }
                    }
                    $table->dropColumn('outlet_id');
                });
            }

            if (! $this->indexExists('plan_visits_archives', 'plan_visits_archives_visitable_date_idx')) {
                Schema::table('plan_visits_archives', function (Blueprint $table) {
                    $table->index(['visitable_type', 'visitable_id', 'tanggal_visit'], 'plan_visits_archives_visitable_date_idx');
                });
            }
        }
    }

    public function down(): void
    {
        // Reverse: add outlet_id back, copy data, drop visitable columns
        foreach (['visits', 'plan_visits', 'visits_archives', 'plan_visits_archives'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'visitable_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('outlet_id')->nullable()->after('user_id');
            });

            DB::table($tableName)
                ->where('visitable_type', 'App\\Models\\Outlet')
                ->update(['outlet_id' => DB::raw('visitable_id')]);

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['visitable_type', 'visitable_id']);
            });
        }
    }

    /**
     * Check if an index exists on a table using raw SQL.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a foreign key exists on a table for a specific column using raw SQL.
     */
    protected function foreignKeyExists(string $table, string $column): bool
    {
        try {
            // For MySQL 5.7+
            $result = DB::select('
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$table, $column]);

            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
