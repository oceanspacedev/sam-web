<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessMediaJob;
use App\Models\Outlet;
use App\Models\Register;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMasterData;
use Tests\Feature\FeatureTestCase;

class LeadMediaQueueTest extends FeatureTestCase
{
    use SeedsMasterData;

    public function test_lead_create_dispatches_media_job_and_stores_temp_paths()
    {
        Queue::fake();
        Storage::fake('public');
        Storage::fake('local');

        $seed = $this->seedMasterData(3);

        $tm = User::create([
            'username' => 'lead-tm',
            'nama_lengkap' => 'Lead TM',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'role_id' => $seed['role']->id,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);

        $user = User::create([
            'username' => 'lead-user',
            'nama_lengkap' => 'Lead User',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'role_id' => $seed['role']->id,
            'tm_id' => $tm->id,
            'password' => bcrypt('secret'),
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'nama_outlet' => 'Lead Queue Outlet',
            'alamat_outlet' => 'Jl. Lead Queue No. 1',
            'nama_pemilik' => 'Budi',
            'nomer_pemilik' => '081234',
            'nomer_perwakilan' => '081235',
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
            'photo3' => UploadedFile::fake()->image('shopsign.jpg'),
            'video' => UploadedFile::fake()->create('vid.mp4', 1500, 'video/mp4'),
        ];

        $response = $this->post('/api/lead', $payload, ['Accept' => 'application/json']);
        $response->assertOk();

        $register = Register::latest()->first();
        $this->assertNotNull($register);
        $this->assertTrue(Str::startsWith($register->poto_depan, 'register/tmp/'));

        $this->assertFalse(
            Outlet::query()->where('kode_outlet', 'LEAD'.$register->id)->exists(),
            'Lead creation should not generate an Outlet record.'
        );

        Queue::assertPushed(ProcessMediaJob::class, function (ProcessMediaJob $job) use ($register) {
            return $job->modelId === $register->id && $job->modelType === 'register' && count($job->mediaItems) === 5;
        });
    }
}
