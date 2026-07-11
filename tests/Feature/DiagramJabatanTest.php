<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Cluster;
use App\Models\Region;
use App\Models\Division;
use App\Models\BadanUsaha;
use App\Filament\Pages\DiagramJabatan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('diagram jabatan counts and lists users under organizational levels', function (): void {
    Cache::forget('diagram_jabatan:tree:v2');

    // Create a cluster
    $bu = BadanUsaha::factory()->create();
    $div = Division::factory()->create(['badanusaha_id' => $bu->id]);
    $reg = Region::factory()->create(['divisi_id' => $div->id, 'badanusaha_id' => $bu->id]);
    $cluster = Cluster::factory()->create(['region_id' => $reg->id, 'divisi_id' => $div->id, 'badanusaha_id' => $bu->id]);

    // Create roles
    $webRole = Role::factory()->create([
        'can_access_web' => true,
        'organizational_scope_level' => 'cluster',
    ]);
    $mobileRole = Role::factory()->create([
        'can_access_web' => false,
        'organizational_scope_level' => 'cluster',
    ]);

    // Create users
    $webUser = User::factory()->create([
        'role_id' => $webRole->id,
    ]);
    $mobileUser = User::factory()->create([
        'role_id' => $mobileRole->id,
    ]);

    // Assign both users to the same cluster
    $webUser->clusters()->attach($cluster->id);
    $mobileUser->clusters()->attach($cluster->id);

    // Call the scopeUserCountsByPivot query through a public or reflection mechanism,
    // or instantiate the DiagramJabatan page class and call the methods.
    $page = new class extends DiagramJabatan {
        public function testUserCounts(string $pivotTable, string $pivotColumn, string $scopeLevel): array
        {
            return $this->scopeUserCountsByPivot($pivotTable, $pivotColumn, $scopeLevel);
        }

        public function testUsersForScope(string $pivotTable, string $pivotColumn, string $scopeLevel, int $scopeId): array
        {
            return $this->scopeUsersForScope($pivotTable, $pivotColumn, $scopeLevel, $scopeId);
        }
    };

    // Verify user count includes both users
    $counts = $page->testUserCounts('user_clusters', 'cluster_id', 'cluster');
    expect($counts[$cluster->id] ?? 0)->toBe(2);

    // Verify user listing includes both users
    $users = $page->testUsersForScope('user_clusters', 'cluster_id', 'cluster', $cluster->id);
    expect($users)->toHaveCount(2);
});

test('diagram jabatan cache is cleared when role, user, or organization levels are updated', function (): void {
    // Write fake data to cache
    Cache::put('diagram_jabatan:tree:v2', ['foo' => 'bar'], 120);
    expect(Cache::has('diagram_jabatan:tree:v2'))->toBeTrue();

    // Trigger role update
    $role = Role::factory()->create();
    expect(Cache::has('diagram_jabatan:tree:v2'))->toBeFalse();

    // Re-put cache
    Cache::put('diagram_jabatan:tree:v2', ['foo' => 'bar'], 120);
    expect(Cache::has('diagram_jabatan:tree:v2'))->toBeTrue();

    // Trigger user update
    $user = User::factory()->create(['role_id' => $role->id]);
    expect(Cache::has('diagram_jabatan:tree:v2'))->toBeFalse();

    // Re-put cache
    Cache::put('diagram_jabatan:tree:v2', ['foo' => 'bar'], 120);
    expect(Cache::has('diagram_jabatan:tree:v2'))->toBeTrue();

    // Trigger user deletion
    $user->delete();
    expect(Cache::has('diagram_jabatan:tree:v2'))->toBeFalse();
});
