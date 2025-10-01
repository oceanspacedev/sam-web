<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename table from 'noos' to 'registers'
        Schema::rename('noos', 'registers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: rename table from 'registers' back to 'noos'
        Schema::rename('registers', 'noos');
    }
};
