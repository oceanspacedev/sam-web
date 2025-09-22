<?php

namespace Tests\Unit\Exports;

use App\Exports\UnvisitedOutletsExport;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UnvisitedOutletsExportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function export_includes_only_outlets_not_visited_in_current_month()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 15));

        $user = User::factory()->create();

        // Buat 3 outlet dalam scope user (divisi/region/cluster sama)
        $outlets = Outlet::factory()->count(3)->create([
            'badanusaha_id' => $user->badanusaha_id,
            'divisi_id' => $user->divisi_id,
            'region_id' => $user->region_id,
            'cluster_id' => $user->cluster_id,
        ]);

        // Tandai 1 outlet sudah divisit bulan ini
        Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlets[0]->id,
            'tanggal_visit' => Carbon::now()->toDateString(),
        ]);

        // Tandai 1 outlet divisit bulan lalu (harus tetap dianggap belum divisit bulan ini)
        Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlets[1]->id,
            'tanggal_visit' => Carbon::now()->subMonth()->startOfMonth()->toDateString(),
        ]);

        $export = new UnvisitedOutletsExport($user);
        $rows = $export->collection();

        // Harus berisi 2 outlet (index 1 & 2)
        $this->assertCount(2, $rows);
        $codes = $rows->pluck('Kode Outlet')->all();
        $this->assertContains($outlets[1]->kode_outlet, $codes);
        $this->assertContains($outlets[2]->kode_outlet, $codes);
        $this->assertNotContains($outlets[0]->kode_outlet, $codes);
    }
}
