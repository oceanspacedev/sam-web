<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('division_settings');
    }

    public function down(): void
    {
        // Intentionally left empty: division_settings is fully replaced by system_settings.
    }
};
