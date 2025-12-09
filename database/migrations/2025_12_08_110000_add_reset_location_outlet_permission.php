<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (! Permission::where('name', 'ResetLocation:Outlet')->exists()) {
            Permission::create([
                'name' => 'ResetLocation:Outlet',
                'guard_name' => 'web',
            ]);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'ResetLocation:Outlet')->delete();
    }
};
