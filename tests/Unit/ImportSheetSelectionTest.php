<?php

use App\Imports\OutletImport;
use App\Imports\PlanVisitImport;

it('only imports the first worksheet for outlet imports', function () {
    $import = new OutletImport('update');

    expect($import->sheets())->toHaveKey(0)
        ->and($import->sheets()[0])->toBe($import);
});

it('only imports the first worksheet for plan visit imports', function () {
    $import = new PlanVisitImport(null, 'daily');

    expect($import->sheets())->toHaveKey(0)
        ->and($import->sheets()[0])->toBe($import);
});
