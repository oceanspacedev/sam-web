<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $this->renamePermission('impersonate', 'Impersonate');
    }

    public function down(): void
    {
        $this->renamePermission('Impersonate', 'impersonate');
    }

    private function renamePermission(string $from, string $to): void
    {
        Permission::with(['roles', 'users'])
            ->where('name', $from)
            ->get()
            ->each(function (Permission $permission) use ($to): void {
                $target = Permission::where('name', $to)
                    ->where('guard_name', $permission->guard_name)
                    ->whereKeyNot($permission->getKey())
                    ->first();

                if (! $target) {
                    $permission->forceFill([
                        'name' => $to,
                        'description' => $permission->description ?: 'Impersonate',
                    ])->save();

                    return;
                }

                $target->roles()->syncWithoutDetaching($permission->roles->pluck('id'));
                $target->users()->syncWithoutDetaching($permission->users->pluck('id'));

                $permission->delete();
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
