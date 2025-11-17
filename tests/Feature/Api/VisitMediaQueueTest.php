<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessMediaJob;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMasterData;
use Tests\Feature\FeatureTestCase;

class VisitMediaQueueTest extends FeatureTestCase
{
    use SeedsMasterData;

    public function test_checkin_queues_media_processing_and_stores_placeholder(): void
    {
        Queue::fake();
        Storage::fake('local');

        $seed = $this->seedMasterData();

        $tm = User::create([
            'username' => 'visit-tm',
            'nama_lengkap' => 'Visit TM',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'role_id' => $seed['role']->id,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);

        $user = User::create([
            'username' => 'visit-user',
            'nama_lengkap' => 'Visit User',
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
            'kode_outlet' => 'VIS001',
            'nama_outlet' => 'Visit Outlet',
            'alamat_outlet' => 'Alamat Visit',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'distric' => 'D01',
            'status_outlet' => 'MAINTAIN',
        ]);

        $response = $this->post('/api/visit', [
            'kode_outlet' => $outlet->kode_outlet,
            'picture_visit' => UploadedFile::fake()->image('checkin.jpg'),
            'latlong_in' => '0,0',
            'tipe_visit' => 'ROUTINE',
        ], ['Accept' => 'application/json']);

        $response->assertOk();

        $visit = Visit::latest()->first();
        $this->assertNotNull($visit);
        $this->assertTrue(Str::startsWith($visit->picture_visit_in, 'tmp/'));

        Queue::assertPushed(ProcessMediaJob::class, function (ProcessMediaJob $job) use ($visit) {
            return $job->modelId === $visit->id && $job->modelType === 'visit' && count($job->mediaItems) === 1;
        });
    }

    public function test_checkout_queues_media_processing_and_stores_placeholder(): void
    {
        Queue::fake();
        Storage::fake('local');

        $seed = $this->seedMasterData();

        $tm = User::create([
            'username' => 'visit-tm-2',
            'nama_lengkap' => 'Visit TM 2',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'role_id' => $seed['role']->id,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);

        $user = User::create([
            'username' => 'visit-user-2',
            'nama_lengkap' => 'Visit User 2',
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
            'kode_outlet' => 'VIS002',
            'nama_outlet' => 'Visit Outlet 2',
            'alamat_outlet' => 'Alamat Visit 2',
            'badanusaha_id' => $seed['bu']->id,
            'divisi_id' => $seed['div']->id,
            'region_id' => $seed['reg']->id,
            'cluster_id' => $seed['clus']->id,
            'distric' => 'D02',
            'status_outlet' => 'MAINTAIN',
        ]);

        $visit = Visit::create([
            'tanggal_visit' => now(),
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tipe_visit' => 'ROUTINE',
            'latlong_in' => '0,0',
            'check_in_time' => now()->subMinutes(30),
            'picture_visit_in' => 'tmp/initial.jpg',
        ]);

        $response = $this->post('/api/visit', [
            'latlong_out' => '0,0',
            'laporan_visit' => 'OK',
            'picture_visit' => UploadedFile::fake()->image('checkout.jpg'),
            'transaksi' => 'YES',
        ], ['Accept' => 'application/json']);

        $response->assertOk();

        $visit->refresh();
        $this->assertTrue(Str::startsWith($visit->picture_visit_out, 'tmp/'));

        Queue::assertPushed(ProcessMediaJob::class, function (ProcessMediaJob $job) use ($visit) {
            return $job->modelId === $visit->id && $job->modelType === 'visit' && count($job->mediaItems) === 1;
        });
    }
}
