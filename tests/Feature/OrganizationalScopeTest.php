<?php

namespace Tests\Feature;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationalScopeTest extends TestCase
{
    use RefreshDatabase;

    protected BadanUsaha $bu;

    protected Division $division;

    protected Region $region;

    protected Cluster $cluster;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organizational hierarchy
        $this->bu = BadanUsaha::factory()->create(['name' => 'Test BU']);
        $this->division = Division::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'name' => 'Test Division',
        ]);
        $this->region = Region::factory()->create([
            'divisi_id' => $this->division->id,
            'badanusaha_id' => $this->bu->id,
            'name' => 'Test Region',
        ]);
        $this->cluster = Cluster::factory()->create([
            'region_id' => $this->region->id,
            'divisi_id' => $this->division->id,
            'badanusaha_id' => $this->bu->id,
            'name' => 'Test Cluster',
        ]);
    }

    /**
     * @group organizational-scope
     *
     * @todo Refine API response structure assertions after production validation
     */
    public function test_super_admin_sees_all_outlets(): void
    {
        $superAdminRole = Role::factory()->create([
            'name' => 'SUPER ADMIN',
            'can_access_web' => 1,
            'organizational_scope_level' => 'all',
        ]);

        $user = User::factory()->create([
            'role_id' => $superAdminRole->id,
        ]);
        // Super admins with 'all' scope still need BU attachment to scope to their business unit
        // but no lower-level attachments (division/region/cluster) to see all within BU
        $user->badanUsahas()->attach($this->bu->id);

        $primaryOutlet = Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $this->division->id,
            'region_id' => $this->region->id,
            'cluster_id' => $this->cluster->id,
        ]);

        $otherDivision = Division::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'name' => 'Alt Division',
        ]);
        $otherRegion = Region::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $otherDivision->id,
            'name' => 'Alt Region',
        ]);
        $otherCluster = Cluster::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $otherDivision->id,
            'region_id' => $otherRegion->id,
            'name' => 'Alt Cluster',
        ]);

        $secondaryOutlet = Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $otherDivision->id,
            'region_id' => $otherRegion->id,
            'cluster_id' => $otherCluster->id,
        ]);

        // Note: With 'all' scope level, super admin sees all outlets regardless of BU
        // Even though query params are provided, they are ignored for 'all' access users
        $thirdOutlet = Outlet::factory()->create();

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/outlet?divisi='.urlencode($this->division->name).'&region='.urlencode($this->region->name));

        $response
            ->assertStatus(200)
            ->assertJsonPath('meta.status', 'success')
            ->assertJsonPath('meta.message', 3);

        $data = collect($response->json('data'));
        $this->assertCount(3, $data);
        $this->assertEqualsCanonicalizing(
            [$primaryOutlet->id, $secondaryOutlet->id, $thirdOutlet->id],
            $data->pluck('id')->all()
        );
    }

    /**
     * @group organizational-scope
     *
     * @todo Refine API response structure assertions after production validation
     */
    public function test_asm_sees_only_their_division_outlets(): void
    {
        $asmRole = Role::factory()->create([
            'name' => 'ASM',
            'can_access_web' => 1,
            'organizational_scope_level' => 'divisi',
        ]);

        $user = User::factory()->create([
            'role_id' => $asmRole->id,
        ]);
        $user->badanUsahas()->attach($this->bu->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        $visibleOutlet = Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $this->division->id,
            'region_id' => $this->region->id,
            'cluster_id' => $this->cluster->id,
        ]);

        $otherDivision = Division::factory()->create([
            'badanusaha_id' => $this->bu->id,
        ]);
        $otherRegion = Region::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $otherDivision->id,
        ]);
        $otherCluster = Cluster::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $otherDivision->id,
            'region_id' => $otherRegion->id,
        ]);
        Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $otherDivision->id,
            'region_id' => $otherRegion->id,
            'cluster_id' => $otherCluster->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/outlet?divisi='.urlencode($this->division->name).'&region='.urlencode($this->region->name));

        $response
            ->assertStatus(200)
            ->assertJsonPath('meta.message', 1);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($visibleOutlet->id, $data[0]['id']);
    }

    /**
     * @group organizational-scope
     *
     * @todo Refine API response structure assertions after production validation
     */
    public function test_asc_sees_only_their_region_outlets(): void
    {
        $ascRole = Role::factory()->create([
            'name' => 'ASC',
            'can_access_web' => 1,
            'organizational_scope_level' => 'cluster',
        ]);

        $user = User::factory()->create([
            'role_id' => $ascRole->id,
        ]);
        $user->badanUsahas()->attach($this->bu->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);
        $user->clusters()->attach($this->cluster->id);

        $visibleOutlet = Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $this->division->id,
            'region_id' => $this->region->id,
            'cluster_id' => $this->cluster->id,
        ]);

        $otherRegion = Region::factory()->create([
            'divisi_id' => $this->division->id,
            'badanusaha_id' => $this->bu->id,
            'name' => 'Secondary Region',
        ]);
        $otherCluster = Cluster::factory()->create([
            'divisi_id' => $this->division->id,
            'region_id' => $otherRegion->id,
            'badanusaha_id' => $this->bu->id,
        ]);
        Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $this->division->id,
            'region_id' => $otherRegion->id,
            'cluster_id' => $otherCluster->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/outlet');

        $response
            ->assertStatus(200)
            ->assertJsonPath('meta.message', 1);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($visibleOutlet->id, $data[0]['id']);
    }

    /**
     * @group organizational-scope
     *
     * @todo Refine API response structure assertions after production validation
     */
    public function test_asc_and_dsf_share_identical_scope_rules(): void
    {
        $ascRole = Role::factory()->create([
            'name' => 'ASC',
            'can_access_web' => 1,
            'organizational_scope_level' => 'cluster',
        ]);

        $dsfRole = Role::factory()->create([
            'name' => 'DSF/DM',
            'can_access_web' => 1,
            'organizational_scope_level' => 'cluster',
        ]);

        $secondaryCluster = Cluster::factory()->create([
            'region_id' => $this->region->id,
            'divisi_id' => $this->division->id,
            'badanusaha_id' => $this->bu->id,
            'name' => 'Secondary Cluster',
        ]);

        $hiddenCluster = Cluster::factory()->create([
            'region_id' => $this->region->id,
            'divisi_id' => $this->division->id,
            'badanusaha_id' => $this->bu->id,
            'name' => 'Hidden Cluster',
        ]);

        $visibleOutlets = [
            Outlet::factory()->create([
                'badanusaha_id' => $this->bu->id,
                'divisi_id' => $this->division->id,
                'region_id' => $this->region->id,
                'cluster_id' => $this->cluster->id,
            ]),
            Outlet::factory()->create([
                'badanusaha_id' => $this->bu->id,
                'divisi_id' => $this->division->id,
                'region_id' => $this->region->id,
                'cluster_id' => $secondaryCluster->id,
            ]),
        ];

        Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $this->division->id,
            'region_id' => $this->region->id,
            'cluster_id' => $hiddenCluster->id,
        ]);

        $ascUser = User::factory()->create([
            'role_id' => $ascRole->id,
        ]);
        $ascUser->badanUsahas()->attach($this->bu->id);
        $ascUser->divisis()->attach($this->division->id);
        $ascUser->regions()->attach($this->region->id);
        $ascUser->clusters()->attach([$this->cluster->id, $secondaryCluster->id]);

        Sanctum::actingAs($ascUser);
        $ascResponse = $this->getJson('/api/outlet');

        $ascResponse
            ->assertStatus(200)
            ->assertJsonPath('meta.message', count($visibleOutlets));

        $ascIds = collect($ascResponse->json('data'))
            ->pluck('id')
            ->sort()
            ->values();

        $expectedIds = collect($visibleOutlets)
            ->pluck('id')
            ->sort()
            ->values();

        $this->assertEquals($expectedIds->all(), $ascIds->all());

        $dsfUser = User::factory()->create([
            'role_id' => $dsfRole->id,
        ]);
        $dsfUser->badanUsahas()->attach($this->bu->id);
        $dsfUser->divisis()->attach($this->division->id);
        $dsfUser->regions()->attach($this->region->id);
        $dsfUser->clusters()->attach([$this->cluster->id, $secondaryCluster->id]);

        Sanctum::actingAs($dsfUser);
        $dsfResponse = $this->getJson('/api/outlet');

        $dsfResponse
            ->assertStatus(200)
            ->assertJsonPath('meta.message', count($visibleOutlets));

        $dsfIds = collect($dsfResponse->json('data'))
            ->pluck('id')
            ->sort()
            ->values();

        $this->assertEquals($ascIds->all(), $dsfIds->all());

        $this->assertEquals(
            Outlet::query()->visibleTo($ascUser)->pluck('id')->sort()->values()->all(),
            Outlet::query()->visibleTo($dsfUser)->pluck('id')->sort()->values()->all()
        );
    }

    public function test_is_visible_to_method_works_correctly(): void
    {
        $ascRole = new Role(['name' => 'ASC', 'can_access_web' => 1, 'organizational_scope_level' => 'cluster']);
        $ascRole->id = 2;
        $ascRole->save();

        $user = User::factory()->create([
            'role_id' => $ascRole->id,
        ]);
        $user->badanUsahas()->attach($this->bu->id);
        $user->divisis()->attach($this->division->id);
        $user->regions()->attach($this->region->id);

        $visibleOutlet = Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $this->division->id,
            'region_id' => $this->region->id,
        ]);

        $otherRegion = Region::factory()->create([
            'divisi_id' => $this->division->id,
            'badanusaha_id' => $this->bu->id,
        ]);
        $hiddenOutlet = Outlet::factory()->create([
            'badanusaha_id' => $this->bu->id,
            'divisi_id' => $this->division->id,
            'region_id' => $otherRegion->id,
        ]);

        $this->assertTrue($visibleOutlet->isVisibleTo($user));
        $this->assertFalse($hiddenOutlet->isVisibleTo($user));
    }
}
