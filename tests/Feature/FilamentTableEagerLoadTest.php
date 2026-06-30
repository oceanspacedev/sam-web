<?php

use App\Filament\Resources\Clusters\ClusterResource;
use App\Filament\Resources\Divisions\DivisionResource;
use App\Filament\Resources\Outlets\OutletResource;
use App\Filament\Resources\Regions\RegionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function createFilamentViewer(): User
{
    $badanUsaha = BadanUsaha::factory()->create(['code' => 'BU-N1', 'name' => 'BU N1']);
    $division = Division::factory()->create([
        'code' => 'DIV-N1',
        'name' => 'DIV N1',
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'code' => 'REG-N1',
        'name' => 'REG N1',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'code' => 'CLUS-N1',
        'name' => 'CLUS N1',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    Outlet::factory()->count(30)->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ]);

    $role = Role::factory()->create([
        'name' => 'N1 VIEWER',
        'can_access_web' => true,
        'organizational_scope_level' => 'cluster',
    ]);

    collect([
        'ViewAny:Outlet',
        'ViewAny:Division',
        'ViewAny:Region',
        'ViewAny:Cluster',
        'ViewAny:Role',
    ])->each(fn (string $name): Permission => Permission::firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]));

    $role->syncPermissions(Permission::query()->whereIn('name', [
        'ViewAny:Outlet',
        'ViewAny:Division',
        'ViewAny:Region',
        'ViewAny:Cluster',
        'ViewAny:Role',
    ])->pluck('name'));

    $viewer = User::factory()->create(['role_id' => $role->id]);
    $viewer->assignRole($role);
    $viewer->badanUsahas()->sync([$badanUsaha->id]);
    $viewer->divisis()->sync([$division->id]);
    $viewer->regions()->sync([$region->id]);
    $viewer->clusters()->sync([$cluster->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $viewer->fresh(['role']);
}

function countTableRelationQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    return count(DB::getQueryLog());
}

function assertQueryCountDoesNotScaleWithRows(User $viewer, callable $loadRows): void
{
    test()->actingAs($viewer);
    $viewer->forgetOrganizationalIdsCache();

    $smallBatch = countTableRelationQueries(fn () => $loadRows(5));
    $largeBatch = countTableRelationQueries(fn () => $loadRows(25));

    expect($largeBatch)->toBeLessThanOrEqual($smallBatch + 1);
}

it('does not n+1 when loading outlet admin table relations', function (): void {
    $viewer = createFilamentViewer();

    assertQueryCountDoesNotScaleWithRows($viewer, function (int $limit): void {
        OutletResource::getEloquentQuery()
            ->limit($limit)
            ->get()
            ->each(function (Outlet $outlet): void {
                $outlet->badanusaha?->name;
                $outlet->divisi?->name;
                $outlet->region?->name;
                $outlet->cluster?->name;
            });
    });
});

it('does not n+1 when loading organizational hierarchy admin tables', function (): void {
    $viewer = createFilamentViewer();

    assertQueryCountDoesNotScaleWithRows($viewer, function (int $limit): void {
        DivisionResource::getEloquentQuery()
            ->limit($limit)
            ->get()
            ->each(fn (Division $division) => $division->badanusaha?->name);
    });

    assertQueryCountDoesNotScaleWithRows($viewer, function (int $limit): void {
        RegionResource::getEloquentQuery()
            ->limit($limit)
            ->get()
            ->each(function (Region $region): void {
                $region->badanusaha?->name;
                $region->divisi?->name;
            });
    });

    assertQueryCountDoesNotScaleWithRows($viewer, function (int $limit): void {
        ClusterResource::getEloquentQuery()
            ->limit($limit)
            ->get()
            ->each(function (Cluster $cluster): void {
                $cluster->badanusaha?->name;
                $cluster->divisi?->name;
                $cluster->region?->name;
            });
    });
});

it('does not n+1 when loading role parent names in admin table', function (): void {
    $viewer = createFilamentViewer();

    $parentRole = Role::factory()->create(['name' => 'N1 PARENT']);
    Role::factory()->count(10)->create(['parent_role_id' => $parentRole->id]);

    assertQueryCountDoesNotScaleWithRows($viewer, function (int $limit): void {
        RoleResource::getEloquentQuery()
            ->limit($limit)
            ->get()
            ->each(fn (Role $role) => $role->parent?->name);
    });
});
