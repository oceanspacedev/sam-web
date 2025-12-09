<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

// ===================================
// Data Integrity Cleanup
// ===================================
// Clean up LEAD outlets (indikasi data kotor)
Schedule::call(function () {
    Log::info('Starting LEAD outlets cleanup job - cleaning all LEAD prefix outlets (data kotor)');

    // Soft delete ALL outlets with LEAD prefix (indikasi data kotor), tanpa pengecualian
    $deletedCount = DB::table('outlets')
        ->where('kode_outlet', 'LIKE', 'LEAD%')
        ->whereNull('outlets.deleted_at')
        ->update(['deleted_at' => now()]);

    Log::info("LEAD outlets cleanup job completed. Soft deleted {$deletedCount} LEAD outlets (data kotor)");
})
->daily()
->at('02:00')
->description('Clean up LEAD outlets (data kotor yang perlu dibersihkan)')
->withoutOverlapping();

// Sync kode_outlet from outlets to registers (fix mismatched kode_outlet)
Schedule::call(function () {
    Log::info('Starting sync kode_outlet from outlets to registers job');

    // Find outlets where register.kode_outlet != outlet.kode_outlet
    $mismatchedOutlets = DB::table('outlets')
        ->join('registers', 'outlets.register_id', '=', 'registers.id')
        ->where('registers.kode_outlet', '!=', 'outlets.kode_outlet')
        ->whereNull('outlets.deleted_at')
        ->whereNull('registers.deleted_at')
        ->where('registers.status', '=', 'APPROVED')
        ->select('outlets.kode_outlet as outlet_kode', 'registers.kode_outlet as register_kode', 'registers.id as register_id')
        ->get();

    $updatedCount = 0;
    foreach ($mismatchedOutlets as $mismatch) {
        DB::table('registers')
            ->where('id', $mismatch->register_id)
            ->update(['kode_outlet' => $mismatch->outlet_kode]);

        $updatedCount++;
        Log::info("Updated register {$mismatch->register_id}: kode_outlet {$mismatch->register_kode} -> {$mismatch->outlet_kode}");
    }

    Log::info("Sync kode_outlet job completed. Updated {$updatedCount} registers to match outlet.kode_outlet");
})
->daily()
->at('03:00')
->description('Sync kode_outlet from outlets to registers (fix mismatches)')
->withoutOverlapping();

// Auto-delete registers with PENDING status or keterangan NULL/EMPTY/LEAD that haven't been updated for 3 months
Schedule::call(function () {
    Log::info('Starting auto-delete old registers job');

    $threeMonthsAgo = now()->subMonths(3);
    $deletedCount = DB::table('registers')
        ->where(function ($query) {
            $query->where('status', '=', 'PENDING')
                ->orWhereNull('keterangan')
                ->orWhere('keterangan', '=', '')
                ->orWhere('keterangan', '=', 'LEAD');
        })
        ->where('updated_at', '<', $threeMonthsAgo)
        ->whereNull('deleted_at')
        ->update(['deleted_at' => now()]);

    Log::info("Auto-delete old registers job completed. Soft deleted {$deletedCount} old registers");
})
->daily()
->at('04:00')
->description('Auto-delete old registers with PENDING status or keterangan NULL/EMPTY/LEAD')
->withoutOverlapping();
