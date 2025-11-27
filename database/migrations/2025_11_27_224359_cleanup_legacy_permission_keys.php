<?php

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renamePermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renamePermissions(reverse: true);
    }

    private function renamePermissions(bool $reverse = false): void
    {
        $mapping = $reverse ? array_flip($this->mapping()) : $this->mapping();

        /** @var Collection<int, Permission> $permissions */
        $permissions = Permission::with(['roles', 'users'])->get();

        foreach ($permissions as $permission) {
            $targetName = $mapping[$permission->name] ?? $permission->name;

            if ($targetName === $permission->name) {
                continue;
            }

            /** @var Permission|null $target */
            $target = Permission::where('name', $targetName)
                ->where('guard_name', $permission->guard_name)
                ->first();

            if (! $target) {
                $permission->forceFill([
                    'name' => $targetName,
                    'description' => $permission->description ?: $this->formatDescription($targetName),
                ])->save();

                continue;
            }

            $target->roles()->syncWithoutDetaching($permission->roles->pluck('id'));
            $target->users()->syncWithoutDetaching($permission->users->pluck('id'));

            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function mapping(): array
    {
        return [
            'view_any_badan::usaha' => 'ViewAny:BadanUsaha',
            'view_badan::usaha' => 'View:BadanUsaha',
            'create_badan::usaha' => 'Create:BadanUsaha',
            'update_badan::usaha' => 'Update:BadanUsaha',
            'delete_badan::usaha' => 'Delete:BadanUsaha',
            'delete_any_badan::usaha' => 'DeleteAny:BadanUsaha',
            'view_any_plan::visit' => 'ViewAny:PlanVisit',
            'view_plan::visit' => 'View:PlanVisit',
            'create_plan::visit' => 'Create:PlanVisit',
            'update_plan::visit' => 'Update:PlanVisit',
            'delete_plan::visit' => 'Delete:PlanVisit',
            'delete_any_plan::visit' => 'DeleteAny:PlanVisit',
            'export_plan::visit' => 'Export:PlanVisit',
        ];
    }

    private function formatDescription(string $permission): string
    {
        return ucwords(str_replace(['_', ':'], ' ', $permission));
    }
};
