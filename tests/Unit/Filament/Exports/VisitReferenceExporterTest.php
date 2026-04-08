<?php

use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Exports\VisitExporter;
use App\Models\PlanVisit;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;

it('uses polymorphic visitable columns in visit exporter', function () {
    $columnNames = collect(VisitExporter::getColumns())
        ->map(fn ($column) => $column->getName())
        ->all();

    expect($columnNames)
        ->toContain('visitable_type')
        ->toContain('tipe_visit')
        ->toContain('visitable_kode_outlet')
        ->toContain('visitable_nama_outlet')
        ->toContain('visitable_badan_usaha')
        ->toContain('visitable_divisi')
        ->toContain('visitable_region')
        ->toContain('visitable_cluster')
        ->not->toContain('visitable')
        ->not->toContain('outlet.kode_outlet')
        ->not->toContain('outlet.nama_outlet')
        ->not->toContain('outlet.divisi.name')
        ->not->toContain('outlet.region.name')
        ->not->toContain('outlet.cluster.name');
});

it('uses polymorphic visitable columns in plan visit exporter', function () {
    $columnNames = collect(PlanVisitExporter::getColumns())
        ->map(fn ($column) => $column->getName())
        ->all();

    expect($columnNames)
        ->toContain('visitable_type')
        ->toContain('visitable_kode_outlet')
        ->toContain('visitable_nama_outlet')
        ->toContain('visitable_badan_usaha')
        ->toContain('visitable_divisi')
        ->toContain('visitable_region')
        ->toContain('visitable_cluster')
        ->not->toContain('outlet.kode_outlet')
        ->not->toContain('outlet.nama_outlet')
        ->not->toContain('outlet.divisi.name')
        ->not->toContain('outlet.region.name')
        ->not->toContain('outlet.cluster.name')
        ->not->toContain('visitable');
});

it('eager loads polymorphic visitable relation for visit exporter', function () {
    $query = VisitExporter::modifyQueryUsing(Visit::query());

    expect($query)
        ->toBeInstanceOf(Builder::class)
        ->and(array_keys($query->getEagerLoads()))
        ->toContain('user.role', 'visitable');
});

it('eager loads polymorphic visitable relation for plan visit exporter', function () {
    $query = PlanVisitExporter::modifyQueryUsing(PlanVisit::query());

    expect($query)
        ->toBeInstanceOf(Builder::class)
        ->and(array_keys($query->getEagerLoads()))
        ->toContain('user', 'visitable');
});
