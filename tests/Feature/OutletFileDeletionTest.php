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
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class OutletFileDeletionTest extends FeatureTestCase
{
    // FeatureTestCase already refreshes DB and fakes storage.

    public function test_old_photo_deleted_when_updating_new_one()
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

        $outlet = Outlet::create([
            'kode_outlet' => 'OUTDEL',
            'nama_outlet' => 'Outlet Del',
            'alamat_outlet' => 'Alamat',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'distric' => 'D01',
            'status_outlet' => 'MAINTAIN',
            'poto_depan' => 'outlets/OUTDEL/photos/old.jpg',
        ]);

        Storage::disk('public')->put('outlets/OUTDEL/photos/old.jpg', 'old');
        $this->assertTrue(Storage::disk('public')->exists('outlets/OUTDEL/photos/old.jpg'));

        $newPhoto = UploadedFile::fake()->image('fotodepan.jpg');
        $resp = $this->post('/api/outlet', [
            'kode_outlet' => $outlet->kode_outlet,
            'nama_pemilik_outlet' => 'Budi',
            'nomer_tlp_outlet' => '08123',
            'latlong' => '0,0',
            'photo0' => $newPhoto,
        ]);
        $resp->assertStatus(200);

        $outlet->refresh();
        $this->assertFalse(Storage::disk('public')->exists('outlets/OUTDEL/photos/old.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($outlet->poto_depan));
    }
}
