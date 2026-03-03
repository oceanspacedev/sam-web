<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;

it('syncs region badan usaha with selected division', function (): void {
    $sourceBadanUsaha = BadanUsaha::factory()->create();
    $wrongBadanUsaha = BadanUsaha::factory()->create();
    $division = Division::factory()->create([
        'badanusaha_id' => $sourceBadanUsaha->id,
    ]);

    $region = Region::factory()->create([
        'divisi_id' => $division->id,
        'badanusaha_id' => $wrongBadanUsaha->id,
    ]);

    expect($region->fresh()->badanusaha_id)->toBe($sourceBadanUsaha->id);
});

it('syncs cluster division and badan usaha with selected region', function (): void {
    $sourceBadanUsaha = BadanUsaha::factory()->create();
    $wrongBadanUsaha = BadanUsaha::factory()->create();

    $sourceDivision = Division::factory()->create([
        'badanusaha_id' => $sourceBadanUsaha->id,
    ]);

    $wrongDivision = Division::factory()->create([
        'badanusaha_id' => $wrongBadanUsaha->id,
    ]);

    $region = Region::factory()->create([
        'divisi_id' => $sourceDivision->id,
        'badanusaha_id' => $sourceBadanUsaha->id,
    ]);

    $cluster = Cluster::factory()->create([
        'region_id' => $region->id,
        'divisi_id' => $wrongDivision->id,
        'badanusaha_id' => $wrongBadanUsaha->id,
    ]);

    $cluster->refresh();

    expect($cluster->divisi_id)->toBe($sourceDivision->id)
        ->and($cluster->badanusaha_id)->toBe($sourceBadanUsaha->id);
});

it('propagates division badan usaha changes to child regions and clusters', function (): void {
    $initialBadanUsaha = BadanUsaha::factory()->create();
    $targetBadanUsaha = BadanUsaha::factory()->create();

    $division = Division::factory()->create([
        'badanusaha_id' => $initialBadanUsaha->id,
    ]);

    $region = Region::factory()->create([
        'divisi_id' => $division->id,
        'badanusaha_id' => $initialBadanUsaha->id,
    ]);

    $cluster = Cluster::factory()->create([
        'region_id' => $region->id,
        'divisi_id' => $division->id,
        'badanusaha_id' => $initialBadanUsaha->id,
    ]);

    $division->update([
        'badanusaha_id' => $targetBadanUsaha->id,
    ]);

    expect($region->fresh()->badanusaha_id)->toBe($targetBadanUsaha->id)
        ->and($cluster->fresh()->badanusaha_id)->toBe($targetBadanUsaha->id);
});

it('propagates region division changes to child clusters', function (): void {
    $initialBadanUsaha = BadanUsaha::factory()->create();
    $targetBadanUsaha = BadanUsaha::factory()->create();

    $initialDivision = Division::factory()->create([
        'badanusaha_id' => $initialBadanUsaha->id,
    ]);

    $targetDivision = Division::factory()->create([
        'badanusaha_id' => $targetBadanUsaha->id,
    ]);

    $region = Region::factory()->create([
        'divisi_id' => $initialDivision->id,
        'badanusaha_id' => $initialBadanUsaha->id,
    ]);

    $cluster = Cluster::factory()->create([
        'region_id' => $region->id,
        'divisi_id' => $initialDivision->id,
        'badanusaha_id' => $initialBadanUsaha->id,
    ]);

    $region->update([
        'divisi_id' => $targetDivision->id,
    ]);

    $region->refresh();
    $cluster->refresh();

    expect($region->badanusaha_id)->toBe($targetBadanUsaha->id)
        ->and($cluster->divisi_id)->toBe($targetDivision->id)
        ->and($cluster->badanusaha_id)->toBe($targetBadanUsaha->id);
});
