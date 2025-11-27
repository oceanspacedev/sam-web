<?php

use App\Models\User;
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
        // Step 1: Add new foreign key columns to registers table
        Schema::table('registers', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('rejected_by_id')->nullable();
            $table->unsignedBigInteger('confirmed_by_id')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
        });

        // Step 2: Migrate data from string names to user IDs (database-agnostic)
        $userMap = User::withTrashed()->pluck('id', 'nama_lengkap')->toArray();

        DB::table('registers')->orderBy('id')->chunk(1000, function ($registers) use ($userMap) {
            foreach ($registers as $register) {
                $updates = [];
                if (! empty($register->created_by) && isset($userMap[$register->created_by])) {
                    $updates['created_by_id'] = $userMap[$register->created_by];
                }
                if (! empty($register->rejected_by) && isset($userMap[$register->rejected_by])) {
                    $updates['rejected_by_id'] = $userMap[$register->rejected_by];
                }
                if (! empty($register->confirmed_by) && isset($userMap[$register->confirmed_by])) {
                    $updates['confirmed_by_id'] = $userMap[$register->confirmed_by];
                }
                if (! empty($register->approved_by) && isset($userMap[$register->approved_by])) {
                    $updates['approved_by_id'] = $userMap[$register->approved_by];
                }
                if (! empty($updates)) {
                    DB::table('registers')->where('id', $register->id)->update($updates);
                }
            }
        });

        // Step 3: Add foreign key constraints
        Schema::table('registers', function (Blueprint $table) {
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('confirmed_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();
        });

        // Step 4: Drop old string columns
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'rejected_by', 'confirmed_by', 'approved_by']);
        });

        // Step 5: Handle registers_archives table
        if (Schema::hasTable('registers_archives')) {
            Schema::table('registers_archives', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->unsignedBigInteger('rejected_by_id')->nullable();
                $table->unsignedBigInteger('confirmed_by_id')->nullable();
                $table->unsignedBigInteger('approved_by_id')->nullable();
            });

            DB::table('registers_archives')->orderBy('id')->chunk(1000, function ($registers) use ($userMap) {
                foreach ($registers as $register) {
                    $updates = [];
                    if (! empty($register->created_by) && isset($userMap[$register->created_by])) {
                        $updates['created_by_id'] = $userMap[$register->created_by];
                    }
                    if (! empty($register->rejected_by) && isset($userMap[$register->rejected_by])) {
                        $updates['rejected_by_id'] = $userMap[$register->rejected_by];
                    }
                    if (! empty($register->confirmed_by) && isset($userMap[$register->confirmed_by])) {
                        $updates['confirmed_by_id'] = $userMap[$register->confirmed_by];
                    }
                    if (! empty($register->approved_by) && isset($userMap[$register->approved_by])) {
                        $updates['approved_by_id'] = $userMap[$register->approved_by];
                    }
                    if (! empty($updates)) {
                        DB::table('registers_archives')->where('id', $register->id)->update($updates);
                    }
                }
            });

            Schema::table('registers_archives', function (Blueprint $table) {
                $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('rejected_by_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('confirmed_by_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('approved_by_id')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('registers_archives', function (Blueprint $table) {
                $table->dropColumn(['created_by', 'rejected_by', 'confirmed_by', 'approved_by']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Build user ID to name map
        $userMap = User::withTrashed()->pluck('nama_lengkap', 'id')->toArray();

        // Restore string columns to registers table
        Schema::table('registers', function (Blueprint $table) {
            $table->string('created_by')->nullable();
            $table->string('rejected_by')->nullable();
            $table->string('confirmed_by')->nullable();
            $table->string('approved_by')->nullable();
        });

        // Migrate data back from IDs to names
        DB::table('registers')->orderBy('id')->chunk(1000, function ($registers) use ($userMap) {
            foreach ($registers as $register) {
                $updates = [];
                if (! empty($register->created_by_id) && isset($userMap[$register->created_by_id])) {
                    $updates['created_by'] = $userMap[$register->created_by_id];
                }
                if (! empty($register->rejected_by_id) && isset($userMap[$register->rejected_by_id])) {
                    $updates['rejected_by'] = $userMap[$register->rejected_by_id];
                }
                if (! empty($register->confirmed_by_id) && isset($userMap[$register->confirmed_by_id])) {
                    $updates['confirmed_by'] = $userMap[$register->confirmed_by_id];
                }
                if (! empty($register->approved_by_id) && isset($userMap[$register->approved_by_id])) {
                    $updates['approved_by'] = $userMap[$register->approved_by_id];
                }
                if (! empty($updates)) {
                    DB::table('registers')->where('id', $register->id)->update($updates);
                }
            }
        });

        // Drop foreign key constraints and columns
        Schema::table('registers', function (Blueprint $table) {
            $table->dropForeign(['created_by_id']);
            $table->dropForeign(['rejected_by_id']);
            $table->dropForeign(['confirmed_by_id']);
            $table->dropForeign(['approved_by_id']);
            $table->dropColumn(['created_by_id', 'rejected_by_id', 'confirmed_by_id', 'approved_by_id']);
        });

        // Restore registers_archives table
        if (Schema::hasTable('registers_archives')) {
            Schema::table('registers_archives', function (Blueprint $table) {
                $table->string('created_by')->nullable();
                $table->string('rejected_by')->nullable();
                $table->string('confirmed_by')->nullable();
                $table->string('approved_by')->nullable();
            });

            DB::table('registers_archives')->orderBy('id')->chunk(1000, function ($registers) use ($userMap) {
                foreach ($registers as $register) {
                    $updates = [];
                    if (! empty($register->created_by_id) && isset($userMap[$register->created_by_id])) {
                        $updates['created_by'] = $userMap[$register->created_by_id];
                    }
                    if (! empty($register->rejected_by_id) && isset($userMap[$register->rejected_by_id])) {
                        $updates['rejected_by'] = $userMap[$register->rejected_by_id];
                    }
                    if (! empty($register->confirmed_by_id) && isset($userMap[$register->confirmed_by_id])) {
                        $updates['confirmed_by'] = $userMap[$register->confirmed_by_id];
                    }
                    if (! empty($register->approved_by_id) && isset($userMap[$register->approved_by_id])) {
                        $updates['approved_by'] = $userMap[$register->approved_by_id];
                    }
                    if (! empty($updates)) {
                        DB::table('registers_archives')->where('id', $register->id)->update($updates);
                    }
                }
            });

            Schema::table('registers_archives', function (Blueprint $table) {
                $table->dropForeign(['created_by_id']);
                $table->dropForeign(['rejected_by_id']);
                $table->dropForeign(['confirmed_by_id']);
                $table->dropForeign(['approved_by_id']);
                $table->dropColumn(['created_by_id', 'rejected_by_id', 'confirmed_by_id', 'approved_by_id']);
            });
        }
    }
};
