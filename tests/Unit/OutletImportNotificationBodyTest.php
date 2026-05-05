<?php

use App\Imports\OutletImport;

it('does not double count skipped outlet import rows in notification body', function () {
    $import = new OutletImport('update');
    $method = new ReflectionMethod($import, 'buildResultBody');
    $method->setAccessible(true);

    $body = $method->invoke($import, 'Update', 696, 0, 481, 6, 215, true);

    expect($body)
        ->toContain('Sebanyak 696 baris diproses.')
        ->toContain('481 baris berhasil diperbarui.')
        ->toContain('215 baris gagal atau dilewati dan perlu diperbaiki.')
        ->toContain('6 baris di antaranya dilewati oleh aturan validasi.')
        ->not->toContain('6 baris dilewati.');
});
