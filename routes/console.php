<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// Archive old data monthly
Schedule::command('data:archive')->monthly()->at('02:00');

// ===================================
// Outlet Lifecycle Maintenance
// ===================================
// Single command that runs all steps sequentially:
// 1. Reactivate UNMAINTAIN with visits
// 2. Cleanup UNPRODUCTIVE
// 3. MAINTAIN → UNMAINTAIN (daily)
// 4. UNMAINTAIN → Archive (Monday only)
Schedule::command('outlets:maintain-lifecycle')
    ->daily()
    ->at('02:00')
    ->appendOutputTo(storage_path('logs/outlet-lifecycle.log'));

// ===================================
// Data Cleanup Maintenance
// ===================================
// Cleanup orphaned territories (divisions/regions/clusters without children)
Schedule::command('territories:cleanup-orphans')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->appendOutputTo(storage_path('logs/territory-cleanup.log'));

// Cleanup user organizational pivot tables based on role scope levels
Schedule::command('users:cleanup-pivots')
    ->weekly()
    ->sundays()
    ->at('03:30')
    ->appendOutputTo(storage_path('logs/user-pivot-cleanup.log'));
