<?php

namespace Tests\Feature;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

class AuthAndValidationEdgeCasesTest extends FeatureTestCase
{
    public function test_protected_route_requires_authentication_returns_401()
    {
        // No Sanctum::actingAs here to simulate unauthenticated request.
        // Use a simple GET endpoint protected by auth:sanctum.
        $resp = $this->getJson('/api/outlet');
        $resp->assertStatus(401);
    }

    public function test_outlet_update_missing_required_fields_returns_422()
    {
        // Build minimal graph and authenticated user
        $bu = BadanUsaha::factory()->create();
        $div = Division::factory()->create(['badanusaha_id' => $bu->id]);
        $reg = Region::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
        $clus = Cluster::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);
        $role = Role::factory()->create(['name' => 'DM', 'can_access_web' => 1]);

        $user = User::create([
            'username' => 'userx',
            'nama_lengkap' => 'User X',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'role_id' => $role->id,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);
        Sanctum::actingAs($user);

        $outlet = Outlet::create([
            'kode_outlet' => 'OUTVAL',
            'nama_outlet' => 'Outlet Val',
            'alamat_outlet' => 'Alamat',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'distric' => 'D01',
            'status_outlet' => 'MAINTAIN',
        ]);

        // Missing required 'latlong' and owner fields should trigger 422
        $photo = UploadedFile::fake()->image('foto.jpg');
        $resp = $this->post('/api/outlet', [
            'kode_outlet' => $outlet->kode_outlet,
            'photo0' => $photo,
        ]);

        $resp->assertStatus(422);
    }
}
