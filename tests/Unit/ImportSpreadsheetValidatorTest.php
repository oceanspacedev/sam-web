<?php

use App\Imports\OutletImport;
use App\Support\ImportSpreadsheetValidator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('normalizes spreadsheet headings like maatwebsite excel', function () {
    expect(ImportSpreadsheetValidator::normalizeHeading('Badan Usaha'))
        ->toBe('badan_usaha')
        ->and(ImportSpreadsheetValidator::normalizeHeadings(['kode_outlet', ' Badan Usaha ']))
        ->toBe(['kode_outlet', 'badan_usaha']);
});

it('detects meaningful row values while ignoring blank placeholders', function () {
    expect(ImportSpreadsheetValidator::rowHasMeaningfulValue(['', '-', null]))->toBeFalse()
        ->and(ImportSpreadsheetValidator::rowHasMeaningfulValue(['', 'OUT-001']))->toBeTrue();
});

it('validates required headings and data rows before queueing import', function () {
    Storage::fake('public');

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['badan_usaha', 'divisi', 'region', 'cluster', 'kode_outlet', 'nama_outlet'],
        ['MSI', 'GROSIR', 'JAKARTA', 'JKT', 'OUT-001', 'TOKO'],
    ]);

    $path = 'import/outlet-create.xlsx';
    Storage::disk('public')->makeDirectory('import');
    $writer = new Xlsx($spreadsheet);
    $writer->save(Storage::disk('public')->path($path));

    ImportSpreadsheetValidator::assertReady('public', $path, [
        'badan_usaha',
        'kode_outlet',
        'nama_outlet',
    ]);

    expect(fn () => ImportSpreadsheetValidator::assertReady('public', $path, ['username']))
        ->toThrow(InvalidArgumentException::class, 'Kolom wajib tidak ditemukan');
});

it('skips blank outlet import rows instead of treating them as validation errors', function () {
    $import = new OutletImport('create');
    $method = new ReflectionMethod($import, 'isBlankImportRow');
    $method->setAccessible(true);

    expect($method->invoke($import, [
        'kode_outlet' => '',
        'badan_usaha' => '-',
    ]))->toBeTrue()
        ->and($method->invoke($import, [
            'kode_outlet' => 'OUT-001',
            'badan_usaha' => '',
        ]))->toBeFalse();
});
