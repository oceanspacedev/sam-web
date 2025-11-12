<?php

use App\Helpers\SendNotif;
use App\Http\Controllers\API\OutletController;
use App\Http\Controllers\API\PlanVisitController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\SyncController;
use App\Http\Controllers\API\TestUploadController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\VisitController;
use App\Http\Controllers\OutletController as outlet;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum', 'logku'])->group(function () {
    // USER
    Route::get('user', [UserController::class, 'fetch']);
    Route::post('logout', [UserController::class, 'logout']);

    // OUTLET
    Route::get('outlet', [OutletController::class, 'fetch']);
    Route::get('outlet/{nama}', [OutletController::class, 'singleOutlet']);
    Route::post('outlet', [OutletController::class, 'updatefoto']);

    // VISIT
    Route::get('visit', [VisitController::class, 'fetch']);
    Route::get('visit/check', [VisitController::class, 'check']);
    Route::post('visit', [VisitController::class, 'submit']);
    Route::get('visit/monitor', [VisitController::class, 'monitor']);

    // PLANVISIT
    Route::get('planvisit', [PlanVisitController::class, 'fetch']);
    Route::post('planvisit', [PlanVisitController::class, 'add']);
    Route::get('planvisit/filter', [PlanVisitController::class, 'bymonth']);
    Route::delete('planvisit', [PlanVisitController::class, 'delete']);
    Route::delete('planvisitrealme', [PlanVisitController::class, 'deleterealme']);
    // PlanVisit NOO endpoints removed

    // Register
    Route::get('register/getbu', [RegisterController::class, 'getbu']);
    Route::get('register/getdiv', [RegisterController::class, 'getdiv']);
    Route::get('register/getreg', [RegisterController::class, 'getreg']);
    Route::get('register/getclus', [RegisterController::class, 'getclus']);
    Route::post('register', [RegisterController::class, 'submitNoo']);
    Route::get('register/all', [RegisterController::class, 'all']);
    Route::get('register', [RegisterController::class, 'fetch']);
    Route::get('register/{kodeOutlet}', [RegisterController::class, 'singleOutlet']);
    Route::get('register/outlet-options', [RegisterController::class, 'getRegisterOutlet']);

    Route::post('register/confirm', [RegisterController::class, 'confirmNoo']);
    Route::post('register/approved', [RegisterController::class, 'approveNoo']);
    Route::post('register/reject', [RegisterController::class, 'rejectNoo']);

    // LEAD
    Route::post('lead', [RegisterController::class, 'submitLead']);
    Route::post('lead/update', [RegisterController::class, 'upgradeLead']);
});

// Route::post('user/register', [UserController::class, 'register']);
Route::post('user/login', [UserController::class, 'login'])->middleware('throttle:login');

Route::post('notif', [SendNotif::class, 'sendMessage']);
Route::post('test-upload', TestUploadController::class);

Route::get('divisi', [SettingController::class, 'getdivisi']);
Route::get('region', [SettingController::class, 'getregion']);

// Sync API Routes - untuk sinkronisasi data
Route::prefix('sync')->middleware('throttle:expensive')->group(function () {
    Route::get('badanusaha', [SyncController::class, 'getBadanUsaha']);
    Route::get('division', [SyncController::class, 'getDivision']);
    Route::get('region', [SyncController::class, 'getRegion']);
    Route::get('cluster', [SyncController::class, 'getCluster']);
    Route::get('role', [SyncController::class, 'getRole']);
    Route::get('user', [SyncController::class, 'getUser']);
    Route::get('outlet', [SyncController::class, 'getOutlet']);
    Route::post('outlet/reset', [SyncController::class, 'resetOutlet']);
    Route::get('visit', [SyncController::class, 'getVisit']);
    Route::get('planvisit', [SyncController::class, 'getPlanVisit']);
    Route::post('visit/create', [SyncController::class, 'createVisit']);
    Route::post('visit/instant', [SyncController::class, 'createInstantVisit']);
    Route::post('visit/instant-delete', [SyncController::class, 'deleteInstantDuplicateVisit']);
    // Route::get('all', [SyncController::class, 'getAllSyncData']);
    // Route::get('by-badanusaha', [SyncController::class, 'getDataByBadanUsaha']);
});
