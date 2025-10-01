<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use App\Support\StorageDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMasterData;

class OutletUpdateFotoTest extends FeatureTestCase
{
    use SeedsMasterData;

    public function test_update_outlet_single_photo_and_video_succeeds()
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

        $outlet = Outlet::create([
            'kode_outlet' => 'OUT001',
            'nama_outlet' => 'Outlet 1',
            'alamat_outlet' => 'Alamat',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'distric' => 'D01',
            'status_outlet' => 'MAINTAIN',
        ]);

        $photo = UploadedFile::fake()->image('fotodepan.jpg', 800, 600);
        $video = UploadedFile::fake()->create('vid.mp4', 1000, 'video/mp4');

        $resp = $this->post('/api/outlet', [
            'kode_outlet' => $outlet->kode_outlet,
            'nama_pemilik_outlet' => 'Budi',
            'nomer_tlp_outlet' => '08123',
            'latlong' => '0,0',
            'photo0' => $photo,
            'video' => $video,
        ]);

        $resp->assertStatus(200)
            ->assertJsonPath('meta.status', 'success');

        $outlet->refresh();
        $this->assertNotNull($outlet->poto_depan);
        $this->assertStringStartsWith('outlets/OUT001/photos/', $outlet->poto_depan);
        $disk = StorageDisk::default();
        $this->assertTrue(Storage::disk($disk)->exists($outlet->poto_depan));

        $this->assertNotNull($outlet->video);
        $this->assertStringStartsWith('outlets/OUT001/videos/', $outlet->video);
        $this->assertTrue(Storage::disk($disk)->exists($outlet->video));
    }
}
