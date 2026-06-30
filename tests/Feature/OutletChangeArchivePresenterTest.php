<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\OutletChangeArchive;
use App\Models\Region;
use App\Support\OrganizationalName;
use App\Support\OutletChangeArchivePresenter;

test('field label maps technical keys to indonesian labels', function (): void {
    expect(OutletChangeArchivePresenter::fieldLabel('alamat_outlet'))->toBe('Alamat Outlet')
        ->and(OutletChangeArchivePresenter::fieldLabel('badanusaha_id'))->toBe('Badan Usaha')
        ->and(OutletChangeArchivePresenter::fieldLabel('poto_depan'))->toBe('Foto Depan');
});

test('summary shows up to three labels and truncates the rest', function (): void {
    expect(OutletChangeArchivePresenter::summary(['alamat_outlet', 'nama_pemilik_outlet']))
        ->toBe('Alamat Outlet, Nama Pemilik Outlet');

    expect(OutletChangeArchivePresenter::summary([
        'alamat_outlet',
        'nama_pemilik_outlet',
        'latlong',
        'poto_depan',
        'poto_kanan',
    ]))->toBe('Alamat Outlet, Nama Pemilik Outlet, +3 lainnya');
});

test('format value resolves organization ids and media fields', function (): void {
    $business = BadanUsaha::factory()->create(['code' => 'BU001', 'name' => 'Badan A']);
    $division = Division::factory()->create([
        'badanusaha_id' => $business->id,
        'code' => 'DIV001',
        'name' => 'Divisi A',
    ]);

    expect(OutletChangeArchivePresenter::formatValue('badanusaha_id', $business->id))
        ->toBe(OrganizationalName::label($business))
        ->and(OutletChangeArchivePresenter::formatValue('divisi_id', $division->id))
        ->toBe(OrganizationalName::label($division))
        ->and(OutletChangeArchivePresenter::formatValue('poto_depan', 'photo.jpg'))
        ->toBe('Ada')
        ->and(OutletChangeArchivePresenter::formatValue('poto_depan', null))
        ->toBe('Kosong')
        ->and(OutletChangeArchivePresenter::formatValue('latlong', '-'))
        ->toBe('Kosong');
});

test('diff rows only include changed fields with formatted values', function (): void {
    $business = BadanUsaha::factory()->create();
    $division = Division::factory()->create(['badanusaha_id' => $business->id]);
    $region = Region::factory()->create([
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $outlet = Outlet::factory()->create([
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
        'alamat_outlet' => 'Alamat Lama',
        'nama_pemilik_outlet' => 'Pemilik Lama',
    ]);

    $archive = OutletChangeArchive::query()->create([
        'outlet_id' => $outlet->id,
        'kode_outlet' => $outlet->kode_outlet,
        'action' => OutletChangeArchive::ACTION_UPDATE,
        'actor_name' => 'Admin Test',
        'old_values' => [
            'alamat_outlet' => 'Alamat Lama',
            'nama_pemilik_outlet' => 'Pemilik Lama',
        ],
        'new_values' => [
            'alamat_outlet' => 'Alamat Baru',
            'nama_pemilik_outlet' => 'Pemilik Lama',
        ],
        'changed_fields' => ['alamat_outlet'],
    ]);

    $rows = OutletChangeArchivePresenter::diffRows($archive);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['label'])->toBe('Alamat Outlet')
        ->and($rows[0]['old'])->toBe('Alamat Lama')
        ->and($rows[0]['new'])->toBe('Alamat Baru');
});

test('status label reflects restore state', function (): void {
    $archive = OutletChangeArchive::query()->create([
        'outlet_id' => null,
        'kode_outlet' => 'OUT001',
        'action' => OutletChangeArchive::ACTION_UPDATE,
        'actor_name' => 'Admin Test',
        'old_values' => [],
        'new_values' => [],
        'changed_fields' => [],
    ]);

    expect(OutletChangeArchivePresenter::statusLabel($archive))->toBe('Aktif');

    $archive->forceFill([
        'restored_at' => now(),
        'restored_by_user_id' => null,
    ])->save();

    expect(OutletChangeArchivePresenter::statusLabel($archive->fresh()))
        ->toStartWith('Direstore ·');
});
