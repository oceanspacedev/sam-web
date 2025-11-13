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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

        $import->onRow($this->makeRow([
            'username' => 'sales01',
            'kode_outlet' => ' otl-001 ',
            'divisi' => ' grosir ',
            'nama_outlet' => 'TOKO A',
            'tanggal_visit' => '2025-01-13',
        ]));

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

        $import->onRow($this->makeRow([
            'username' => 'sales01',
            'kode_outlet' => 'OTL-001',
            'divisi' => 'GROSIR',
            'nama_outlet' => 'TOKO A',
            'tanggal_visit' => '2025-01-13',
        ]));

        $existing->refresh();
        $this->assertEquals('2025-01-13', $existing->tanggal_visit);
        $this->assertEquals(1, PlanVisit::count());
    }

    public function test_records_error_when_tanggal_visit_within_one_week(): void
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

        $import->onRow($this->makeRow([
            'username' => 'sales01',
            'kode_outlet' => 'OTL-001',
            'divisi' => 'GROSIR',
            'nama_outlet' => 'TOKO A',
            'tanggal_visit' => '2025-01-07',
        ]));

        $summary = Cache::get($import->getSummaryKey());

        $this->assertEquals(1, $summary['error_total']);
        $this->assertDatabaseCount('plan_visits', 0);
    }

    public function test_records_error_when_user_division_does_not_match_outlet_division(): void
    {
        $badanUsaha = BadanUsaha::factory()->create(['name' => 'MSI']);
        $division = Division::factory()->create([
            'name' => 'GROSIR',
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $divisionRegion = Region::factory()->create([
            'name' => 'JAKARTA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
        ]);
        $divisionCluster = Cluster::factory()->create([
            'name' => 'JKT-UTARA',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $divisionRegion->id,
        ]);

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

        User::factory()->create([
            'username' => 'sales01',
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $otherDivision->id,
            'region_id' => $otherRegion->id,
            'cluster_id' => $otherCluster->id,
        ]);

        Outlet::factory()->create([
            'badanusaha_id' => $badanUsaha->id,
            'divisi_id' => $division->id,
            'region_id' => $divisionRegion->id,
            'cluster_id' => $divisionCluster->id,
            'kode_outlet' => 'OTL-001',
        ]);

        $import = new PlanVisitImport;

        $import->onRow($this->makeRow([
            'username' => 'sales01',
            'kode_outlet' => 'OTL-001',
            'divisi' => 'GROSIR',
            'nama_outlet' => 'TOKO A',
            'tanggal_visit' => '2025-01-13',
        ]));

        $summary = Cache::get($import->getSummaryKey());

        $this->assertEquals(1, $summary['error_total']);
        $this->assertDatabaseCount('plan_visits', 0);
    }

    private function makeRow(array $data, int $rowIndex = 2): Row
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headings = array_keys($data);
        $sheet->fromArray([$headings, array_values($data)]);

        $row = $sheet->getRowIterator($rowIndex)->current();

        return new Row($row, $headings, array_fill(0, count($headings), false));
    }
}
