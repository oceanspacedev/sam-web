<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NooController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PlanVisitController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('admin');
});

// #HANDLE JETSTREAM BLOCK
// Route::get('/', [LoginController::class,'index']);
Route::get('/login', [LoginController::class, 'index'])->name('login');

// #LOGIN
Route::get('/masuk', [LoginController::class, 'index'])->name('masuk');
Route::post('/masuk', [LoginController::class, 'login'])->middleware('throttle:login');

// #EXPORT
// #USER
Route::get('/user/export', [UserController::class, 'export'])->name('user.export')->middleware('throttle:expensive');
Route::get('/user/export/template', [UserController::class, 'template'])->name('user.template');

// #OUTLET
Route::get('/outlet/export', [OutletController::class, 'export'])->name('outlet.export')->middleware('throttle:expensive');
Route::get('/outlet/export/template', [OutletController::class, 'template']);

// #NOO
Route::get('/noo/export', [NooController::class, 'export'])->name('noo.export')->middleware('throttle:expensive');

// #VISIT
Route::get('/visit/export', [VisitController::class, 'export'])->name('visit.export')->middleware('throttle:expensive');

// #PLANVISIT
Route::get('/planvisit/export', [PlanVisitController::class, 'export'])->name('planvisit.export')->middleware('throttle:expensive');
Route::get('/planvisit/export/template', [PlanVisitController::class, 'template']);

// IMPORT
// #USER
Route::post('/user/import', [UserController::class, 'import'])->name('user.import')->middleware('throttle:expensive');

// #OUTLET
Route::post('/outlet/import', [OutletController::class, 'import'])->middleware('throttle:expensive');

// #PLAN VISIT
Route::post('/planvisit/import', [PlanVisitController::class, 'import'])->middleware('throttle:expensive');

// #MIDDLEWARE
Route::middleware(['auth', 'isAdmin'])->group(function () {

    // #DASHBOARD
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/logout', [DashboardController::class, 'logout']);

    // #NOO
    Route::get('/noo', [NooController::class, 'index'])->name('noo');
    Route::get('/noo/{id}', [NooController::class, 'show']);
    Route::post('noo/{id}', [NooController::class, 'update']);

    // #OUTLET
    Route::get('/outlet', [OutletController::class, 'index'])->name('outlet');
    Route::get('/outlet/delete/{id}', [OutletController::class, 'delete']);
    Route::get('/outlet/{id}', [OutletController::class, 'edit']);
    Route::post('outlet/{id}', [OutletController::class, 'update']);

    // #USER
    Route::get('/user', [UserController::class, 'index'])->name('user');
    Route::post('/user', [UserController::class, 'store']);
    Route::get('/user/{id}', [UserController::class, 'edit']);
    Route::post('/user/{id}', [UserController::class, 'update']);

    // #VISIT
    Route::get('/visit', [VisitController::class, 'index']);
    Route::get('/visit/{id}', [VisitController::class, 'show']);

    // #PLANVISIT
    Route::get('/planvisit', [PlanVisitController::class, 'index']);
    Route::post('/planvisit/store', [PlanVisitController::class, 'store']);

    // #REPORT
    Route::get('/report/noo', [ReportController::class, 'nooMounth']);

    // #SETTING
    // #ROLE
    Route::get('/setting/role', [SettingController::class, 'role']);
    Route::get('/setting/role/{id}', [SettingController::class, 'roleedit']);
    Route::post('/setting/role', [SettingController::class, 'roleadd']);
    Route::post('/setting/role/{id}', [SettingController::class, 'roleupdate']);

    // #BADAN USAHA
    Route::get('/setting/badanusaha', [SettingController::class, 'badanusaha']);
    Route::get('/setting/badanusaha/{id}', [SettingController::class, 'buedit']);
    Route::post('/setting/badanusaha', [SettingController::class, 'buadd']);
    Route::post('/setting/badanusaha/{id}', [SettingController::class, 'buupdate']);

    // DIVISI
    Route::get('/setting/divisi', [SettingController::class, 'divisi']);
    Route::get('/setting/divisi/{id}', [SettingController::class, 'divedit']);
    Route::post('/setting/divisi', [SettingController::class, 'divadd']);
    Route::post('/setting/divisi/{id}', [SettingController::class, 'divupdate']);

    // #REGION
    Route::get('/setting/region', [SettingController::class, 'region']);
    Route::get('/setting/region/{id}', [SettingController::class, 'regedit']);
    Route::post('/setting/region', [SettingController::class, 'regadd']);
    Route::post('/setting/region/{id}', [SettingController::class, 'regupdate']);

    // #CLUSTER
    Route::get('/setting/cluster', [SettingController::class, 'cluster']);
    Route::get('/setting/cluster/{id}', [SettingController::class, 'clusedit']);
    Route::post('/setting/cluster', [SettingController::class, 'clusadd']);
    Route::post('/setting/cluster/{id}', [SettingController::class, 'clusupdate']);

    // #LINK SELECT
    Route::get('/setting/getdivisi', [SettingController::class, 'getdivisi']);
    Route::get('/setting/getregion', [SettingController::class, 'getregion']);
});

Route::get('download/app', [SettingController::class, 'download'])->middleware('throttle:expensive');

Route::get('terms-and-conditions', function () {
    return view('terms_and_conditions');
});

// Route::get('/tes',function (Request $request){
//     // Log::channel('custom')->info('halo');
//     // phpinfo();
//     dd(today());
// });

// Route::get('destroyuser',[UserController::class,'destroyall']);
// Route::get('destroyoutlet',[OutletController::class,'destroyall']);
