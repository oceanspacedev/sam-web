<?php

namespace Tests\Feature;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMasterData;

class RegisterFlowTest extends FeatureTestCase
{
    use SeedsMasterData;

    private function seedGraph(): array
    {
        $bu = BadanUsaha::factory()->create();
        $div = Division::factory()->create(['badanusaha_id' => $bu->id]);
        $reg = Region::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
        $clus = Cluster::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);
        $role = new Role(['name' => 'DM', 'can_access_web' => 1]);
        $role->id = 99;
        $role->save();
        $ascRole = new Role(['name' => 'ASC', 'can_access_web' => 1]);
        $ascRole->id = 2;
        $ascRole->save();
        $arRole = new Role(['name' => 'AR', 'can_access_web' => 1]);
        $arRole->id = 4;
        $arRole->save();

        $tm = User::create([
            'username' => 'tm1',
            'nama_lengkap' => 'TM One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'role_id' => $role->id,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);

        $user = User::create([
            'username' => 'user1',
            'nama_lengkap' => 'User One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'role_id' => $role->id,
            'tm_id' => $tm->id,
            'password' => bcrypt('secret'),
        ]);

        // ASC user to satisfy optional lookup in controller
        User::create([
            'username' => 'asc1',
            'nama_lengkap' => 'ASC One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'role_id' => 2,
            'tm_id' => $tm->id,
            'id_notif' => 'asc-notif',
            'password' => bcrypt('secret'),
        ]);

        // AR user for notif lookup
        User::create([
            'username' => 'ar1',
            'nama_lengkap' => 'AR One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
            'role_id' => 4,
            'tm_id' => $tm->id,
            'id_notif' => 'ar-notif',
            'password' => bcrypt('secret'),
        ]);

        Sanctum::actingAs($user);

        return compact('bu', 'div', 'reg', 'clus', 'role', 'tm', 'user');
    }

    public function test_register_submit_confirm_approve_creates_outlet()
    {
        $g = $this->seedGraph();

        $photoFront = UploadedFile::fake()->image('fotodepan.jpg');
        $photoRight = UploadedFile::fake()->image('fotokanan.jpg');
        $photoLeft = UploadedFile::fake()->image('fotokiri.jpg');
        $photoKtp = UploadedFile::fake()->image('fotoktp.jpg');
        $photoSign = UploadedFile::fake()->image('sign.jpg');
        $video = UploadedFile::fake()->create('vid.mp4', 1000, 'video/mp4');

        $payload = [
            'nama_outlet' => 'Register Test',
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
            'photo3' => $photoKtp,
            'photo4' => $photoSign,
            'video' => $video,
        ];

        $resp = $this->post('/api/register', $payload);
        $resp->assertStatus(200);

        $register = Register::latest()->first();
        $this->assertNotNull($register);

        // Confirm
        $confirm = $this->postJson('/api/register/confirm', [
            'id' => $register->id,
            'status' => 'CONFIRMED',
            'limit' => 1000000,
            'kode_outlet' => 'REG-001',
        ]);
        $confirm->assertStatus(200);

        // Approve
        $approve = $this->postJson('/api/register/approved', [
            'id' => $register->id,
            'status' => 'APPROVED',
        ]);
        $approve->assertStatus(200);

        $this->assertDatabaseHas('outlets', [
            'kode_outlet' => 'REG-001',
            'nama_outlet' => $register->nama_outlet,
            'status_outlet' => 'MAINTAIN',
        ]);
    }

    public function test_register_submit_and_reject_sets_status()
    {
        $g = $this->seedGraph();

        $photoFront = UploadedFile::fake()->image('fotodepan.jpg');
        $photoRight = UploadedFile::fake()->image('fotokanan.jpg');
        $photoLeft = UploadedFile::fake()->image('fotokiri.jpg');
        $photoKtp = UploadedFile::fake()->image('fotoktp.jpg');
        $photoSign = UploadedFile::fake()->image('sign.jpg');
        $video = UploadedFile::fake()->create('vid.mp4', 1000, 'video/mp4');

        $payload = [
            'nama_outlet' => 'Register Test 2',
            'alamat_outlet' => 'Alamat Y',
            'nama_pemilik' => 'Susi',
            'nomer_pemilik' => '08123',
            'nomer_perwakilan' => '08124',
            'ktpnpwp' => '5678',
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
            'photo3' => $photoKtp,
            'photo4' => $photoSign,
            'video' => $video,
        ];

        $resp = $this->post('/api/register', $payload);
        $resp->assertStatus(200);

        $register = Register::latest()->first();
        $reject = $this->postJson('/api/register/reject', [
            'id' => $register->id,
            'status' => 'REJECTED',
            'alasan' => 'Tidak memenuhi syarat',
        ]);
        $reject->assertStatus(200);

        $this->assertDatabaseHas('registers', [
            'id' => $register->id,
            'status' => 'REJECTED',
        ]);
    }
}
