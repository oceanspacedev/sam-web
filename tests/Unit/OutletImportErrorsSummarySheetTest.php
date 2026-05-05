<?php

use App\Exports\Outlet\OutletImportErrorsSummarySheet;

it('keeps blank outlet import error cells blank', function () {
    $sheet = new OutletImportErrorsSummarySheet([
        [
            'row' => 12,
            'message' => 'Cluster tidak ditemukan.',
            'kode_outlet' => '010.012',
            'columns' => [
                'kode_outlet' => '010.012',
                'cluster_baru' => 'MALANG-PASURUAN-BLITAR',
            ],
        ],
    ], 'update');

    $row = $sheet->collection()->first();

    expect($sheet->headings())->not->toContain('Baris Excel')
        ->and($row['badan_usaha_baru'])->toBeNull();
});
