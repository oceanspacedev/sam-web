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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutletUpdateEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function seedMasterData(): array
    {
        $bu = BadanUsaha::create(['name' => 'BU']);
        $div = Division::create(['name' => 'DIV', 'badanusaha_id' => $bu->id]);
        $reg = Region::create(['name' => 'REG', 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
        $clus = Cluster::create(['name' => 'CLUS', 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);
        $role = Role::create(['name' => 'DM', 'can_access_web' => 1]);

        return compact('bu','div','reg','clus','role');
    }

    private function actingUser(array $seed)
    {
        $tm = User::create([
            'username' => 'tm1',
            'nama_lengkap' => 'TM One',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'role_id' => $seed['role']->id,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);

        $user = User::create([
            'username' => 'user1',
            'nama_lengkap' => 'User One',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'role_id' => $seed['role']->id,
            'tm_id' => $tm->id,
            'password' => bcrypt('secret'),
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createOutlet(array $seed): Outlet
    {
        return Outlet::create([
            'kode_outlet' => 'OUT999',
            'nama_outlet' => 'Outlet X',
            'alamat_outlet' => 'Alamat',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'distric' => 'D01',
            'status_outlet' => 'MAINTAIN',
        ]);
    }

    public function test_single_photo_without_keyword_sets_shop_sign()
    {
        $seed = $this->seedMasterData();
        $this->actingUser($seed);
        $outlet = $this->createOutlet($seed);

        $photo = UploadedFile::fake()->image('random.jpg', 640, 480);
        $resp = $this->post('/api/outlet', [
            'kode_outlet' => $outlet->kode_outlet,
            'nama_pemilik_outlet' => 'Budi',
            'nomer_tlp_outlet' => '08123',
            'latlong' => '0,0',
            'photo0' => $photo,
        ]);

        if ($resp->status() !== 200) {
            $resp->dump();
        }
        if ($resp->status() !== 200) {
            $resp->dump();
        }
        if ($resp->status() !== 200) { $resp->dump(); }
        $resp->assertStatus(200);
        $outlet->refresh();
        $this->assertNotNull($outlet->poto_shop_sign);
        Storage::disk('public')->assertExists($outlet->poto_shop_sign);
    }

    public function test_reject_invalid_mime_photo()
    {
        $seed = $this->seedMasterData();
        $this->actingUser($seed);
        $outlet = $this->createOutlet($seed);

        $bad = UploadedFile::fake()->create('bad.txt', 1, 'text/plain');
        $resp = $this->post('/api/outlet', [
            'kode_outlet' => $outlet->kode_outlet,
            'nama_pemilik_outlet' => 'Budi',
            'nomer_tlp_outlet' => '08123',
            'latlong' => '0,0',
            'photo0' => $bad,
        ]);

        $resp->assertStatus(422);
    }

    public function test_reject_oversize_photo()
    {
        $seed = $this->seedMasterData();
        $this->actingUser($seed);
        $outlet = $this->createOutlet($seed);

        $big = UploadedFile::fake()->image('big.jpg')->size(6000); // ~6MB
        $resp = $this->post('/api/outlet', [
            'kode_outlet' => $outlet->kode_outlet,
            'nama_pemilik_outlet' => 'Budi',
            'nomer_tlp_outlet' => '08123',
            'latlong' => '0,0',
            'photo0' => $big,
        ]);

        $resp->assertStatus(422);
    }

    public function test_supports_multiple_photos_via_indexed_fields()
    {
        $seed = $this->seedMasterData();
        $this->actingUser($seed);
        $outlet = $this->createOutlet($seed);

        $photoFront = UploadedFile::fake()->image('fotodepan.jpg');
        $photoRight = UploadedFile::fake()->image('fotokanan.jpg');
        $resp = $this->post('/api/outlet', [
            'kode_outlet' => $outlet->kode_outlet,
            'nama_pemilik_outlet' => 'Budi',
            'nomer_tlp_outlet' => '08123',
            'latlong' => '0,0',
            'photo0' => $photoFront,
            'photo1' => $photoRight,
        ]);

        $resp->assertStatus(200);
        $outlet->refresh();
        $this->assertNotNull($outlet->poto_depan);
        $this->assertNotNull($outlet->poto_kanan);
        Storage::disk('public')->assertExists($outlet->poto_depan);
        Storage::disk('public')->assertExists($outlet->poto_kanan);
    }
}
