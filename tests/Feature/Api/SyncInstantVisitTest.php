<?php

namespace Tests\Feature\Api;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use App\Support\StorageDisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncInstantVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $disk = StorageDisk::default();
        Storage::fake($disk);

        if ($disk !== 'public') {
            Storage::fake('public');
        }
    }

    public function test_create_instant_visit_success(): void
    {
        $user = User::factory()->create([
            'username' => 'tester',
        ]);
        $outlet = Outlet::factory()->create();

        $payload = [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tipe_visit' => 'Sales Call',
            'latlong_in' => '-6.2,106.8',
            'latlong_out' => '-6.21,106.81',
            'laporan_visit' => 'All good',
            'transaksi' => 'OK',
            'picture_visit_in' => UploadedFile::fake()->image('in.jpg', 100, 100),
            'picture_visit_out' => UploadedFile::fake()->image('out.jpg', 100, 100),
        ];

        $response = $this->postJson('/api/sync/visit/instant', $payload);
        $response->assertCreated();
        $response->assertJsonStructure([
            'meta' => ['code', 'status', 'message'],
            'data' => [
                'visit' => [
                    'id', 'user_id', 'outlet_id', 'tipe_visit', 'latlong_in', 'latlong_out',
                    'check_in_time', 'check_out_time', 'durasi_visit', 'laporan_visit', 'transaksi',
                    'picture_visit_in', 'picture_visit_out', 'tanggal_visit', 'created_at', 'updated_at',
                ],
            ],
        ]);

        $visit = Visit::first();
        $this->assertNotNull($visit);
        $disk = StorageDisk::default();

        $this->assertTrue(Storage::disk($disk)->exists($visit->picture_visit_in));
        $this->assertTrue(Storage::disk($disk)->exists($visit->picture_visit_out));
    }

    public function test_create_instant_visit_rejects_duplicate_same_day_same_outlet(): void
    {
        $user = User::factory()->create();
        $outlet = Outlet::factory()->create();

        // First create
        $this->postJson('/api/sync/visit/instant', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tipe_visit' => 'Sales Call',
            'latlong_in' => '-6.2,106.8',
            'latlong_out' => '-6.21,106.81',
            'laporan_visit' => 'All good',
            'transaksi' => 'OK',
            'picture_visit_in' => UploadedFile::fake()->image('in.jpg', 100, 100),
            'picture_visit_out' => UploadedFile::fake()->image('out.jpg', 100, 100),
        ])->assertCreated();

        // Second attempt should be rejected
        $this->postJson('/api/sync/visit/instant', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tipe_visit' => 'Sales Call',
            'latlong_in' => '-6.2,106.8',
            'latlong_out' => '-6.21,106.81',
            'laporan_visit' => 'All good',
            'transaksi' => 'OK',
            'picture_visit_in' => UploadedFile::fake()->image('in2.jpg', 100, 100),
            'picture_visit_out' => UploadedFile::fake()->image('out2.jpg', 100, 100),
        ])->assertStatus(422);

        $this->assertEquals(1, Visit::count());
    }
}
