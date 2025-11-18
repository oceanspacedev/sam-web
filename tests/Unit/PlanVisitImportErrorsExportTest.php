<?php

namespace Tests\Unit;

use App\Exports\PlanVisitImportErrorsExport;
use Tests\TestCase;

class PlanVisitImportErrorsExportTest extends TestCase
{
    public function test_daily_summary_sheet_uses_daily_columns(): void
    {
        $rows = [[
            'message' => 'Tanggal wajib diisi',
            'columns' => [
                'username' => 'sales01',
                'kode_outlet' => 'OTL-001',
                'divisi' => 'GROSIR',
                'nama_outlet' => 'Toko A',
                'tanggal_visit' => '2025-01-13',
            ],
        ]];

        $sheet = (new PlanVisitImportErrorsExport($rows, 'daily'))->sheets()[0];
        $firstRow = $sheet->collection()->first();

        $this->assertArrayHasKey('tanggal_visit', $firstRow);
        $this->assertArrayNotHasKey('schedule_week', $firstRow);
        $this->assertSame('Tanggal wajib diisi', $firstRow['message']);
    }

    public function test_weekly_summary_sheet_uses_weekly_columns(): void
    {
        $rows = [[
            'message' => 'Week tidak valid',
            'columns' => [
                'username' => 'sales01',
                'kode_outlet' => 'OTL-001',
                'divisi' => 'TECNO',
                'nama_outlet' => 'Toko B',
                'schedule_week' => 'Week 2',
                'schedule_year' => '2025',
            ],
        ]];

        $sheet = (new PlanVisitImportErrorsExport($rows, 'weekly'))->sheets()[0];
        $firstRow = $sheet->collection()->first();

        $this->assertArrayHasKey('schedule_week', $firstRow);
        $this->assertArrayHasKey('schedule_year', $firstRow);
        $this->assertArrayNotHasKey('tanggal_visit', $firstRow);
        $this->assertSame('Week tidak valid', $firstRow['message']);
    }
}
