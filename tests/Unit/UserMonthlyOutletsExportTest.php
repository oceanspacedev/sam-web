<?php

use App\Exports\User\UserMonthlyOutletsExport;
use App\Models\User;

test('UserMonthlyOutletsExport includes 6 cycle sheets for outlets, registers, daily/weekly plans, visits, and unvisited outlets', function () {
    $user = User::factory()->make(['id' => 662]);
    $export = new UserMonthlyOutletsExport($user);

    $sheets = $export->sheets();

    expect($sheets)->toHaveCount(6);
    expect($sheets[0]->title())->toBe('All Outlets');
    expect($sheets[1]->title())->toBe('All Registers');
    expect($sheets[2]->title())->toBe('Plan Daily (2026-08)');
    expect($sheets[3]->title())->toBe('Plan Weekly (2026-08)');
    expect($sheets[4]->title())->toBe('Visits (2026-08)');
    expect($sheets[5]->title())->toBe('Unvisited (2026-08)');
});
