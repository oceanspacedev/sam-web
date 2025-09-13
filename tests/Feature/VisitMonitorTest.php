<?php

namespace Tests\Feature;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function makeGraph(int $divisiId, int $regionId)
    {
        $bu = BadanUsaha::factory()->create();
        $div = Division::factory()->create(['badanusaha_id' => $bu->id, 'id' => $divisiId]);
        $reg = Region::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'id' => $regionId]);
        $clus = Cluster::factory()->create(['badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);
        return compact('bu','div','reg','clus');
    }

    public function test_monitor_for_special_user_id_2_merges_visits()
    {
        $g = $this->makeGraph(8, 63);
        $role = Role::factory()->create(['name' => 'CSO', 'can_access_web' => 1]);

        // Manager user id=2, role 8
        $manager = new User([
            'username' => 'mgr2',
            'nama_lengkap' => 'Manager Two',
            'badanusaha_id' => $g['bu']->id,
            'divisi_id' => $g['div']->id,
            'region_id' => $g['reg']->id,
            'cluster_id' => $g['clus']->id,
            'role_id' => 8,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);
        $manager->id = 2;
        $manager->save();

        // Sales user in divisi 8, region in allowed list
        $sales = User::create([
            'username' => 'sales1',
            'nama_lengkap' => 'Sales One',
            'badanusaha_id' => $g['bu']->id,
            'divisi_id' => 8,
            'region_id' => 63,
            'cluster_id' => $g['clus']->id,
            'role_id' => $role->id,
            'tm_id' => $manager->id,
            'password' => bcrypt('secret'),
        ]);

        $outlet = Outlet::factory()->create([
            'badanusaha_id' => $g['bu']->id,
            'divisi_id' => 8,
            'region_id' => 63,
            'cluster_id' => $g['clus']->id,
        ]);
        
        // Create Visit via API as sales to ensure date format compatibility
        \Laravel\Sanctum\Sanctum::actingAs($sales);
        $photoIn = \Illuminate\Http\UploadedFile::fake()->image('in.jpg');
        $this->post('/api/visit', [
            'kode_outlet' => $outlet->kode_outlet,
            'picture_visit' => $photoIn,
            'latlong_in' => '0,0',
            'tipe_visit' => 'ROUTINE',
        ])->assertStatus(200);

        $outlet2 = Outlet::factory()->create([
            'badanusaha_id' => $g['bu']->id,
            'divisi_id' => 8,
            'region_id' => 63,
            'cluster_id' => $g['clus']->id,
        ]);
        $photoIn2 = \Illuminate\Http\UploadedFile::fake()->image('in2.jpg');
        $this->post('/api/visit', [
            'kode_outlet' => $outlet2->kode_outlet,
            'picture_visit' => $photoIn2,
            'latlong_in' => '0,0',
            'tipe_visit' => 'ROUTINE',
        ])->assertStatus(200);

        \Laravel\Sanctum\Sanctum::actingAs($manager);
        $resp = $this->getJson('/api/visit/monitor');
        $resp->assertStatus(200);
        $data = $resp->json('data');
        if (count($data) < 2) { $resp->dump(); }
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_monitor_for_special_user_id_689_divisi_11()
    {
        $g = $this->makeGraph(11, 63);

        $manager = new User([
            'username' => 'mgr689',
            'nama_lengkap' => 'Manager 689',
            'badanusaha_id' => $g['bu']->id,
            'divisi_id' => 11,
            'region_id' => $g['reg']->id,
            'cluster_id' => $g['clus']->id,
            'role_id' => 8,
            'tm_id' => 1,
            'password' => bcrypt('secret'),
        ]);
        $manager->id = 689;
        $manager->save();

        $sales = User::create([
            'username' => 'sales2',
            'nama_lengkap' => 'Sales Two',
            'badanusaha_id' => $g['bu']->id,
            'divisi_id' => 11,
            'region_id' => $g['reg']->id,
            'cluster_id' => $g['clus']->id,
            'role_id' => Role::factory()->create()->id,
            'tm_id' => $manager->id,
            'password' => bcrypt('secret'),
        ]);

        $outlet = Outlet::factory()->create([
            'badanusaha_id' => $g['bu']->id,
            'divisi_id' => 11,
            'region_id' => $g['reg']->id,
            'cluster_id' => $g['clus']->id,
        ]);

        Visit::create([
            'tanggal_visit' => Carbon::now()->toDateString(),
            'user_id' => $sales->id,
            'outlet_id' => $outlet->id,
            'tipe_visit' => 'ROUTINE',
            'latlong_in' => '0,0',
            'check_in_time' => Carbon::now(),
        ]);

        Sanctum::actingAs($manager);
        $resp = $this->getJson('/api/visit/monitor');
        $resp->assertStatus(200);
        $this->assertCount(1, $resp->json('data'));
    }
}
