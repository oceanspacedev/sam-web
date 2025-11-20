<?php

namespace Tests\Feature;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserObserverPivotCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected BadanUsaha $badanUsaha;

    protected Division $division;

    protected Region $region;

    protected Cluster $cluster;

    protected Role $roleAll;

    protected Role $roleBadanUsaha;

    protected Role $roleDivisi;

    protected Role $roleRegion;

    protected Role $roleCluster;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organizational hierarchy
        $this->badanUsaha = BadanUsaha::factory()->create();
        $this->division = Division::factory()->create(['badanusaha_id' => $this->badanUsaha->id]);
        $this->region = Region::factory()->create([
            'badanusaha_id' => $this->badanUsaha->id,
            'divisi_id' => $this->division->id,
        ]);
        $this->cluster = Cluster::factory()->create([
            'badanusaha_id' => $this->badanUsaha->id,
            'divisi_id' => $this->division->id,
            'region_id' => $this->region->id,
        ]);

        // Create roles with different scope levels
        $this->roleAll = Role::factory()->create(['organizational_scope_level' => 'all']);
        $this->roleBadanUsaha = Role::factory()->create(['organizational_scope_level' => 'badanusaha']);
        $this->roleDivisi = Role::factory()->create(['organizational_scope_level' => 'divisi']);
        $this->roleRegion = Role::factory()->create(['organizational_scope_level' => 'region']);
        $this->roleCluster = Role::factory()->create(['organizational_scope_level' => 'cluster']);
    }

    /** @test */
    public function it_removes_all_organizational_pivots_when_user_has_all_scope()
    {
        $user = User::factory()->create(['role_id' => $this->roleCluster->id]);

        // Assign all organizational levels
        $user->badanUsahas()->attach($this->badanUsaha->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        // Change to 'all' scope
        $user->update(['role_id' => $this->roleAll->id]);

        // Assert all pivots are removed
        $this->assertEquals(0, $user->badanUsahas()->count());
        $this->assertEquals(0, $user->divisis()->count());
        $this->assertEquals(0, $user->regions()->count());
        $this->assertEquals(0, $user->clusters()->count());
    }

    /** @test */
    public function it_removes_lower_level_pivots_when_user_has_badanusaha_scope()
    {
        $user = User::factory()->create(['role_id' => $this->roleCluster->id]);

        // Assign all organizational levels
        $user->badanUsahas()->attach($this->badanUsaha->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        // Change to 'badanusaha' scope
        $user->update(['role_id' => $this->roleBadanUsaha->id]);

        // Assert badan usaha remains, others removed
        $this->assertEquals(1, $user->badanUsahas()->count());
        $this->assertEquals(0, $user->divisis()->count());
        $this->assertEquals(0, $user->regions()->count());
        $this->assertEquals(0, $user->clusters()->count());
    }

    /** @test */
    public function it_removes_region_and_cluster_pivots_when_user_has_divisi_scope()
    {
        $user = User::factory()->create(['role_id' => $this->roleCluster->id]);

        // Assign all organizational levels
        $user->badanUsahas()->attach($this->badanUsaha->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        // Change to 'divisi' scope
        $user->update(['role_id' => $this->roleDivisi->id]);

        // Assert badan usaha and divisi remain, region and cluster removed
        $this->assertEquals(1, $user->badanUsahas()->count());
        $this->assertEquals(1, $user->divisis()->count());
        $this->assertEquals(0, $user->regions()->count());
        $this->assertEquals(0, $user->clusters()->count());
    }

    /** @test */
    public function it_removes_only_cluster_pivots_when_user_has_region_scope()
    {
        $user = User::factory()->create(['role_id' => $this->roleCluster->id]);

        // Assign all organizational levels
        $user->badanUsahas()->attach($this->badanUsaha->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        // Change to 'region' scope
        $user->update(['role_id' => $this->roleRegion->id]);

        // Assert only cluster is removed
        $this->assertEquals(1, $user->badanUsahas()->count());
        $this->assertEquals(1, $user->divisis()->count());
        $this->assertEquals(1, $user->regions()->count());
        $this->assertEquals(0, $user->clusters()->count());
    }

    /** @test */
    public function it_keeps_all_pivots_when_user_has_cluster_scope()
    {
        $user = User::factory()->create(['role_id' => $this->roleCluster->id]);

        // Assign all organizational levels
        $user->badanUsahas()->attach($this->badanUsaha->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        // Trigger update (no role change)
        $user->touch();

        // Assert all pivots remain
        $this->assertEquals(1, $user->badanUsahas()->count());
        $this->assertEquals(1, $user->divisis()->count());
        $this->assertEquals(1, $user->regions()->count());
        $this->assertEquals(1, $user->clusters()->count());
    }

    /** @test */
    public function it_cleans_up_on_update_changing_scope_to_divisi()
    {
        // Create user with cluster scope (so pivots won't be cleaned initially)
        $user = User::factory()->create(['role_id' => $this->roleCluster->id]);

        // Manually attach pivots (simulating a user with full organizational assignments)
        $user->badanUsahas()->attach($this->badanUsaha->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        // Now change to divisi scope - this should trigger cleanup
        $user->update(['role_id' => $this->roleDivisi->id]);

        // Assert region and cluster are cleaned, while badanusaha and divisi remain
        $this->assertEquals(1, $user->badanUsahas()->count());
        $this->assertEquals(1, $user->divisis()->count());
        $this->assertEquals(0, $user->regions()->count());
        $this->assertEquals(0, $user->clusters()->count());
    }
}
