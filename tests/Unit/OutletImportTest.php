<?php

namespace Tests\Unit;

use App\Imports\OutletImport;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_outlet_from_create_template_row(): void
    {
        $badanUsaha = BadanUsaha::factory()->create(['name' => 'MSI']);
        $division = Division::factory()->create([
            'name' => 'GROSIR',
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $region = Region::factory()->create([
            'name' => 'JAKARTA',
            'divisi_id' => $division->id,
            'badanusaha_id' => $badanUsaha->id,
        ]);
        Cluster::factory()->create([
            'name' => 'JKT-UTARA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
        ]);

        $row = [
            'badan_usaha' => 'MSI',
            'divisi' => 'GROSIR',
            'region' => 'JAKARTA',
            'cluster' => 'JKT-UTARA',
            'kode_outlet' => 'grosir001',
            'nama_outlet' => 'Toko Maju Jaya',
            'alamat_outlet' => 'Jl. Raya No. 1',
            'distric' => 'Penjaringan',
            'limit' => 0,
        ];

        $import = new OutletImport('create');
        $model = $import->model($row);

        $this->assertInstanceOf(Outlet::class, $model);

        $model->save();

        $outlet = Outlet::firstOrFail();

        $this->assertEquals($division->id, $outlet->divisi_id);
        $this->assertEquals('GROSIR001', $outlet->kode_outlet);
        $this->assertEquals('TOKO MAJU JAYA', $outlet->nama_outlet);
        $this->assertEquals('JL. RAYA NO. 1', $outlet->alamat_outlet);
        $this->assertEquals('PENJARINGAN', $outlet->distric);
        $this->assertEquals(0, $outlet->limit);
        $this->assertEquals('MAINTAIN', $outlet->status_outlet);
        $this->assertNotNull($outlet->cluster);
        $this->assertEquals('JKT-UTARA', $outlet->cluster->name);
        $this->assertEquals('JAKARTA', $outlet->region->name);
    }

    public function test_updates_existing_outlet_from_update_template_row(): void
    {
        $badanUsaha = BadanUsaha::factory()->create(['name' => 'MSI']);
        $division = Division::factory()->create([
            'name' => 'GROSIR',
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $region = Region::factory()->create([
            'name' => 'JAKARTA',
            'divisi_id' => $division->id,
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $cluster = Cluster::factory()->create([
            'name' => 'JKT-UTARA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
        ]);
        $newCluster = Cluster::factory()->create([
            'name' => 'JKT-SELATAN',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
        ]);

        $outlet = Outlet::factory()->create([
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'kode_outlet' => 'GROSIR001',
            'nama_outlet' => 'TOKO MAJU JAYA',
            'status_outlet' => 'MAINTAIN',
            'limit' => 0,
            'distric' => 'PENJARINGAN',
        ]);

        $row = [
            'badan_usaha' => 'MSI',
            'divisi' => 'GROSIR',
            'region' => 'JAKARTA',
            'cluster' => 'JKT-UTARA',
            'kode_outlet' => 'GROSIR001',
            'nama_outlet' => 'TOKO MAJU JAYA',
            'distric' => 'Penjaringan',
            'limit' => 0,
            'status_outlet' => 'MAINTAIN',
            'cluster_baru' => 'JKT-SELATAN',
            'kode_outlet_baru' => 'GROSIR002',
            'nama_outlet_baru' => 'Toko Maju Sejahtera',
            'limit_baru' => 10,
            'status_outlet_baru' => 'OPEN',
            'distric_baru' => 'Kebayoran',
            'nama_pemilik_outlet_baru' => 'Susi',
            'nomer_tlp_outlet_baru' => '0811111111',
        ];

        $import = new OutletImport('update');
        $result = $import->model($row);

        $this->assertNull($result);

        $outlet->refresh();

        $this->assertEquals('GROSIR002', $outlet->kode_outlet);
        $this->assertEquals('TOKO MAJU SEJAHTERA', $outlet->nama_outlet);
        $this->assertEquals('KEBAYORAN', $outlet->distric);
        $this->assertEquals(10, $outlet->limit);
        $this->assertEquals('OPEN', $outlet->status_outlet);
        $this->assertEquals('SUSI', $outlet->nama_pemilik_outlet);
        $this->assertEquals('0811111111', $outlet->nomer_tlp_outlet);

        $updatedCluster = $outlet->cluster;
        $this->assertEquals($newCluster->id, $updatedCluster->id);
        $this->assertEquals('JKT-SELATAN', $updatedCluster->name);
        $this->assertEquals(2, Cluster::count());
    }
}
