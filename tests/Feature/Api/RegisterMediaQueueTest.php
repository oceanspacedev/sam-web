<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessMediaJob;
use App\Jobs\SendNotificationJob;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMasterData;
use Tests\Feature\FeatureTestCase;

class RegisterMediaQueueTest extends FeatureTestCase
{
    use SeedsMasterData;

    private function seedGraph(): array
    {
        $bu = BadanUsaha::factory()->create(['name' => 'Test BU']);
        $division = Division::factory()->create([
            'badanusaha_id' => $bu->id,
            'name' => 'Test Division',
        ]);
        $region = Region::factory()->create([
            'badanusaha_id' => $bu->id,
            'divisi_id' => $division->id,
            'name' => 'Test Region',
        ]);
        $cluster = Cluster::factory()->create([
            'badanusaha_id' => $bu->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'name' => 'Test Cluster',
        ]);

        $roleDm = new Role(['name' => 'DM', 'can_access_web' => 1]);
        $roleDm->id = 99;
        $roleDm->save();

        $roleAsc = new Role(['name' => 'ASC', 'can_access_web' => 1]);
        $roleAsc->id = 2;
        $roleAsc->save();

        $roleAr = new Role(['name' => 'AR', 'can_access_web' => 1]);
        $roleAr->id = 4;
        $roleAr->save();

        $tm = User::create([
            'username' => 'tm-queue',
            'nama_lengkap' => 'TM One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'role_id' => $roleDm->id,
            'tm_id' => 1,
            'id_notif' => 'tm-notif',
            'password' => bcrypt('secret'),
        ]);

        $user = User::create([
            'username' => 'user-queue',
            'nama_lengkap' => 'User One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'role_id' => $roleDm->id,
            'tm_id' => $tm->id,
            'id_notif' => 'user-notif',
            'password' => bcrypt('secret'),
        ]);

        User::create([
            'username' => 'asc-queue',
            'nama_lengkap' => 'ASC One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'role_id' => $roleAsc->id,
            'tm_id' => $tm->id,
            'id_notif' => 'asc-notif',
            'password' => bcrypt('secret'),
        ]);

        User::create([
            'username' => 'ar-queue',
            'nama_lengkap' => 'AR One',
            'badanusaha_id' => $bu->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'role_id' => $roleAr->id,
            'tm_id' => $tm->id,
            'id_notif' => 'ar-notif',
            'password' => bcrypt('secret'),
        ]);

        Sanctum::actingAs($user);

        return compact('bu', 'division', 'region', 'cluster', 'user', 'tm');
    }

    public function test_submit_dispatches_media_processing_job_and_persists_temp_files()
    {
        Queue::fake();
        Storage::fake('public');
        Storage::fake('local');

        $this->seedGraph();

        $payload = [
            'nama_outlet' => 'Outlet Queue',
            'alamat_outlet' => 'Jl. Queue No. 1',
            'nama_pemilik' => 'Budi',
            'nomer_pemilik' => '081234',
            'nomer_perwakilan' => '081235',
            'ktpnpwp' => '123456789',
            'distric' => 'D01',
            'oppo' => '0',
            'vivo' => '0',
            'samsung' => '0',
            'xiaomi' => '0',
            'realme' => '0',
            'fl' => '0',
            'latlong' => '0,0',
            'photo0' => UploadedFile::fake()->image('fotodepan.jpg'),
            'photo1' => UploadedFile::fake()->image('fotokanan.jpg'),
            'photo2' => UploadedFile::fake()->image('fotokiri.jpg'),
            'photo3' => UploadedFile::fake()->image('fotoktp.jpg'),
            'photo4' => UploadedFile::fake()->image('shopsign.jpg'),
            'video' => UploadedFile::fake()->create('vid.mp4', 2000, 'video/mp4'),
        ];

        $response = $this->post('/api/register', $payload, ['Accept' => 'application/json']);

        // Debug: Check response status and content
        echo "Response status: " . $response->getStatusCode() . PHP_EOL;
        echo "Response content: " . $response->getContent() . PHP_EOL;

        $response->assertOk();

        $register = Register::latest()->first();
        $this->assertNotNull($register);
        $this->assertTrue(Str::startsWith($register->poto_depan, 'register/tmp/'));

        // Debug: Check what the response contains
        $responseData = $response->json();
        if (isset($responseData['debug'])) {
            echo "Debug info: " . json_encode($responseData['debug']) . PHP_EOL;
        }

        // Check that media job was dispatched
        Queue::assertPushed(ProcessMediaJob::class, function ($job) use ($register) {
            return $job->modelType === 'register' && $job->modelId === $register->id;
        });

        // Check that notification job was also dispatched
        Queue::assertPushed(SendNotificationJob::class, 1);
    }
}
