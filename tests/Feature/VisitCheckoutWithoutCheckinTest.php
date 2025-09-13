<?php

namespace Tests\Feature;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitCheckoutWithoutCheckinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_checkout_without_checkin_returns_422()
    {
        $bu = BadanUsaha::factory()->create();
        $div = Division::factory()->create(['badanusaha_id' => $bu->id]);
        $reg = Region::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
        $clus = Cluster::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);
        $role = Role::factory()->create(['name' => 'DM', 'can_access_web' => 1]);

        $user = User::create([
            'username' => 'user',
            'nama_lengkap' => 'User',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'role_id' => $role->id,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);
        Sanctum::actingAs($user);

        $photoOut = UploadedFile::fake()->image('out.jpg');
        $resp = $this->post('/api/visit', [
            'latlong_out' => '0,0',
            'laporan_visit' => 'OK',
            'picture_visit' => $photoOut,
            'transaksi' => 'YES',
        ]);
        $resp->assertStatus(422);
    }
}
