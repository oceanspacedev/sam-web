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
use Tests\Feature\FeatureTestCase;
use Tests\Concerns\SeedsMasterData;

class VisitFlowTest extends FeatureTestCase
{
    use SeedsMasterData;

    // Storage is already faked in FeatureTestCase.

    public function test_visit_checkin_and_checkout_flow()
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

        // Check-in
        $photoIn = UploadedFile::fake()->image('in.jpg', 640, 480);
        $respIn = $this->post('/api/visit', [
            'kode_outlet' => $outlet->kode_outlet,
            'picture_visit' => $photoIn,
            'latlong_in' => '0,0',
            'tipe_visit' => 'ROUTINE',
        ]);
        $respIn->assertStatus(200)->assertJsonPath('meta.status', 'success');

        // Check-out (use latest)
        $photoOut = UploadedFile::fake()->image('out.jpg', 640, 480);
        $respOut = $this->post('/api/visit', [
            'latlong_out' => '0,0',
            'laporan_visit' => 'OK',
            'picture_visit' => $photoOut,
            'transaksi' => 'YES',
        ]);
        $respOut->assertStatus(200)->assertJsonPath('meta.status', 'success');

        // Assert files stored
        $inPath = $respIn->json('data.visit.picture_visit_in');
        $outPath = $respOut->json('data.visit.picture_visit_out');
        $this->assertNotEmpty($inPath);
        $this->assertNotEmpty($outPath);
    $this->assertTrue(Storage::disk('public')->exists($inPath));
    $this->assertTrue(Storage::disk('public')->exists($outPath));
    }
}
