<?php

namespace Tests\Unit;

use App\Imports\PlanVisitImport;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanVisitImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2025-01-06 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_creates_plan_visit_using_username_and_division(): void
    {
        $badanUsaha = BadanUsaha::factory()->create(['name' => 'MSI']);
        $division = Division::factory()->create([
            'name' => 'GROSIR',
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $region = Region::factory()->create([
            'name' => 'JAKARTA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
        ]);
        $cluster = Cluster::factory()->create([
            'name' => 'JKT-UTARA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'username' => 'sales01',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
        ]);

        /** @var Outlet $primaryOutlet */
        $primaryOutlet = Outlet::factory()->create([
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'kode_outlet' => 'OTL-001',
        ]);

        // Tambahkan outlet dengan kode sama di divisi berbeda untuk memastikan pencarian spesifik divisi
        $otherDivision = Division::factory()->create([
            'name' => 'RETAIL',
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $otherRegion = Region::factory()->create([
            'name' => 'BANDUNG',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $otherDivision->id,
        ]);
        $otherCluster = Cluster::factory()->create([
            'name' => 'BDG-SELATAN',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $otherDivision->id,
            'region_id' => $otherRegion->id,
        ]);
        Outlet::factory()->create([
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $otherDivision->id,
            'region_id' => $otherRegion->id,
            'cluster_id' => $otherCluster->id,
            'kode_outlet' => 'OTL-001',
        ]);

        $import = new PlanVisitImport;
        $model = $import->model([
            'username' => 'sales01',
            'kode_outlet' => ' otl-001 ',
            'divisi' => ' grosir ',
            'nama_outlet' => 'TOKO A',
            'tanggal_visit' => '2025-01-13',
        ]);

        $this->assertInstanceOf(PlanVisit::class, $model);

        $model->save();

        $this->assertDatabaseHas('plan_visits', [
            'user_id' => $user->id,
            'outlet_id' => $primaryOutlet->id,
            'tanggal_visit' => '2025-01-13',
        ]);
    }

    public function test_updates_existing_plan_visit_when_duplicate_exists(): void
    {
        $badanUsaha = BadanUsaha::factory()->create(['name' => 'MSI']);
        $division = Division::factory()->create([
            'name' => 'GROSIR',
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $region = Region::factory()->create([
            'name' => 'JAKARTA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
        ]);
        $cluster = Cluster::factory()->create([
            'name' => 'JKT-UTARA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'username' => 'sales01',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
        ]);

        /** @var Outlet $outlet */
        $outlet = Outlet::factory()->create([
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'kode_outlet' => 'OTL-001',
        ]);

        Carbon::setTestNow(Carbon::parse('2025-01-05 09:00:00'));

        $existing = PlanVisit::create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => '2025-01-13',
        ]);

        Carbon::setTestNow(Carbon::parse('2025-01-06 09:00:00'));

        $import = new PlanVisitImport;
        $result = $import->model([
            'username' => 'sales01',
            'kode_outlet' => 'OTL-001',
            'divisi' => 'GROSIR',
            'nama_outlet' => 'TOKO A',
            'tanggal_visit' => '2025-01-13',
        ]);

        $this->assertNull($result);

        $existing->refresh();
        $this->assertEquals('2025-01-13', $existing->tanggal_visit);
        $this->assertEquals(1, PlanVisit::count());
    }

    public function test_throws_exception_when_tanggal_visit_within_one_week(): void
    {
        $badanUsaha = BadanUsaha::factory()->create(['name' => 'MSI']);
        $division = Division::factory()->create([
            'name' => 'GROSIR',
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $region = Region::factory()->create([
            'name' => 'JAKARTA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
        ]);
        $cluster = Cluster::factory()->create([
            'name' => 'JKT-UTARA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
        ]);

        User::factory()->create([
            'username' => 'sales01',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
        ]);

        Outlet::factory()->create([
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $region->id,
            'cluster_id' => $cluster->id,
            'kode_outlet' => 'OTL-001',
        ]);

        $import = new PlanVisitImport;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('minimal harus satu minggu ke depan');

        $import->model([
            'username' => 'sales01',
            'kode_outlet' => 'OTL-001',
            'divisi' => 'GROSIR',
            'nama_outlet' => 'TOKO A',
            'tanggal_visit' => '2025-01-07',
        ]);
    }
}
