<?php

namespace Tests\Feature;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Noo;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadFlowTest extends TestCase
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
        $role = new Role(['name' => 'DM', 'can_access_web' => 1]);
        $role->id = 3; // avoid case 1/2/9/10 in controller
        $role->save();

        return compact('bu','div','reg','clus','role');
    }

    public function test_lead_create_creates_noo_and_lead_outlet_and_saves_files()
    {
        $seed = $this->seedMasterData();

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

        // Provide all 4 photos + video to satisfy NOT NULL columns in noos table
        $photoFront = UploadedFile::fake()->image('fotodepan.jpg');
        $photoRight = UploadedFile::fake()->image('fotokanan.jpg');
        $photoLeft = UploadedFile::fake()->image('fotokiri.jpg');
        $photoSign = UploadedFile::fake()->image('shopsign.jpg');
        $video = UploadedFile::fake()->create('vid.mp4', 1000, 'video/mp4');

        $payload = [
            'nama_outlet' => 'Lead Outlet',
            'alamat_outlet' => 'Alamat X',
            'nama_pemilik' => 'Budi',
            'nomer_pemilik' => '08123',
            'nomer_perwakilan' => '08124',
            'ktpnpwp' => '1234',
            'distric' => 'D01',
            'oppo' => '0',
            'vivo' => '0',
            'samsung' => '0',
            'xiaomi' => '0',
            'realme' => '0',
            'fl' => '0',
            'latlong' => '0,0',
            'photo0' => $photoFront,
            'photo1' => $photoRight,
            'photo2' => $photoLeft,
            'photo3' => $photoSign,
            'video' => $video,
        ];

        $resp = $this->post('/api/lead', $payload);
        if ($resp->status() !== 200) { $resp->dump(); }
        $resp->assertStatus(200);

        // Assert NOO created
        $noo = Noo::latest()->first();
        $this->assertNotNull($noo);
        $this->assertNotEmpty($noo->poto_depan);
        $this->assertNotEmpty($noo->poto_kanan);
        $this->assertNotEmpty($noo->poto_kiri);
        $this->assertNotEmpty($noo->poto_shop_sign);
        $this->assertNotEmpty($noo->video);

        Storage::disk('public')->assertExists($noo->poto_depan);
        Storage::disk('public')->assertExists($noo->poto_kanan);
        Storage::disk('public')->assertExists($noo->poto_kiri);
        Storage::disk('public')->assertExists($noo->poto_shop_sign);
        Storage::disk('public')->assertExists($noo->video);

        // Assert a lead outlet created with code LEAD{noo_id}
        $leadOutlet = Outlet::where('kode_outlet', 'LEAD' . $noo->id)->first();
        $this->assertNotNull($leadOutlet);
        $this->assertEquals('Lead Outlet', $leadOutlet->nama_outlet);
    }
}
