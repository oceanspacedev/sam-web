<?php

use App\Exports\PlanVisit\PlanVisitImportErrorsSummarySheet;

it('keeps blank plan visit import error cells blank', function () {
    $sheet = new PlanVisitImportErrorsSummarySheet([
        [
            'row' => 8,
            'message' => 'Outlet tidak ditemukan.',
            'kode_outlet' => '010.012',
            'columns' => [
                'username' => 'sales01',
                'kode_outlet' => '010.012',
                'divisi' => 'MSIS',
            ],
        ],
    ], 'daily');

    $row = $sheet->collection()->first();

    expect($sheet->headings())->not->toContain('Baris Excel')
        ->and($row['tanggal_visit'])->toBeNull();
});
