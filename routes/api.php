<?php

use App\Helpers\SendNotif;
use App\Http\Controllers\API\NooController;
use App\Http\Controllers\API\LeadController;
use App\Http\Controllers\API\OutletController;
use App\Http\Controllers\API\PlanVisitController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\VisitController;
use App\Http\Controllers\API\SyncController;
use App\Http\Controllers\SettingController;
use Carbon\Carbon;
use FFMpeg\FFMpeg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OutletController as outlet;

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
    //USER
    Route::get('user', [UserController::class, 'fetch']);
    Route::post('logout', [UserController::class, 'logout']);

    //OUTLET
    Route::get('outlet', [OutletController::class, 'fetch']);
    Route::get('outlet/{nama}', [OutletController::class, 'singleOutlet']);
    Route::post('outlet', [OutletController::class, 'updatefoto']);


    //VISIT
    Route::get('visit', [VisitController::class, 'fetch']);
    Route::get('visit/check', [VisitController::class, 'check']);
    Route::post('visit', [VisitController::class, 'submit']);
    Route::get('visit/monitor', [VisitController::class, 'monitor']);


    //PLANVISIT
    Route::get('planvisit', [PlanVisitController::class, 'fetch']);
    Route::post('planvisit', [PlanVisitController::class, 'add']);
    Route::get('planvisit/filter', [PlanVisitController::class, 'bymonth']);
    Route::delete('planvisit', [PlanVisitController::class, 'delete']);
    Route::delete('planvisitrealme', [PlanVisitController::class, 'deleterealme']);
    // PlanVisit NOO endpoints removed

    //NOO
    Route::get('noo/getbu', [NooController::class, 'getbu']);
    Route::get('noo/getdiv', [NooController::class, 'getdiv']);
    Route::get('noo/getreg', [NooController::class, 'getreg']);
    Route::get('noo/getclus', [NooController::class, 'getclus']);
    Route::post('noo', [NooController::class, 'submit']);
    Route::get('noo/all', [NooController::class, 'all']);
    Route::get('noo', [NooController::class, 'fetch']);
    Route::get('noo/{kodeOutlet}', [NooController::class, 'singleOutlet']);
    Route::get('nooOutlet', [NooController::class, 'getnoooutlet']);

    Route::post('noo/confirm', [NooController::class, 'confirm']);
    Route::post('noo/approved', [NooController::class, 'approved']);
    Route::post('noo/reject', [NooController::class, 'reject']);

    //LEAD
    Route::post('lead', [LeadController::class, 'create']);
    Route::post('lead/update', [LeadController::class, 'update']);
});

// Route::post('user/register', [UserController::class, 'register']);
Route::post('user/login', [UserController::class, 'login']);

Route::post('notif', [SendNotif::class, 'sendMessage']);

Route::get('divisi', [SettingController::class, 'getdivisi']);
Route::get('region', [SettingController::class, 'getregion']);

// Sync API Routes - untuk sinkronisasi data
Route::prefix('sync')->group(function () {
    Route::get('badanusaha', [SyncController::class, 'getBadanUsaha']);
    Route::get('division', [SyncController::class, 'getDivision']);
    Route::get('region', [SyncController::class, 'getRegion']);
    Route::get('cluster', [SyncController::class, 'getCluster']);
    Route::get('role', [SyncController::class, 'getRole']);
    Route::get('user', [SyncController::class, 'getUser']);
    Route::get('outlet', [SyncController::class, 'getOutlet']);
    Route::get('visit', [SyncController::class, 'getVisit']);
    Route::get('planvisit', [SyncController::class, 'getPlanVisit']);
    Route::post('visit/create', [SyncController::class, 'createVisit']);
    // Route::get('all', [SyncController::class, 'getAllSyncData']);
    // Route::get('by-badanusaha', [SyncController::class, 'getDataByBadanUsaha']);
});
