<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rename('Reset:AnyOutlet', 'Reset:Outlet');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rename('Reset:Outlet', 'Reset:AnyOutlet');
    }

    private function rename(string $from, string $to): void
    {
        /** @var Permission|null $source */
        $source = Permission::withTrashed()->where('name', $from)->first();

        if (! $source) {
            return;
        }

        /** @var Permission|null $target */
        $target = Permission::withTrashed()->where('name', $to)
            ->where('guard_name', $source->guard_name)
            ->first();

        if (! $target) {
            $source->forceFill([
                'name' => $to,
                'description' => $source->description ?: ucwords(str_replace(['_', ':'], ' ', $to)),
            ])->save();

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $target->roles()->syncWithoutDetaching($source->roles->pluck('id'));
        $target->users()->syncWithoutDetaching($source->users->pluck('id'));

        $source->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
