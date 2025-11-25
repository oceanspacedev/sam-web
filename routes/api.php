<?php

use App\Helpers\SendNotif;
use App\Http\Controllers\API\OutletController;
use App\Http\Controllers\API\PlanVisitController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\VisitController;
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
    Route::get('noo/getbu', [RegisterController::class, 'getbu']);
    Route::get('noo/getdiv', [RegisterController::class, 'getdiv']);
    Route::get('noo/getreg', [RegisterController::class, 'getreg']);
    Route::get('noo/getclus', [RegisterController::class, 'getclus']);
    Route::post('noo', [RegisterController::class, 'submitNoo']);
    Route::get('noo/all', [RegisterController::class, 'all']);
    Route::get('noo', [RegisterController::class, 'fetch']);
    Route::get('noo/{kodeOutlet}', [RegisterController::class, 'singleOutlet']);
    Route::get('nooOutlet', [RegisterController::class, 'getRegisterOutlet']);

    Route::post('noo/confirm', [RegisterController::class, 'confirmNoo']);
    Route::post('noo/approved', [RegisterController::class, 'approveNoo']);
    Route::post('noo/reject', [RegisterController::class, 'rejectNoo']);

    // LEAD
    Route::post('lead', [RegisterController::class, 'submitLead']);
    Route::post('lead/update', [RegisterController::class, 'upgradeLead']);

    // SETTINGS - Organization hierarchy helpers (MOVED FROM UNAUTHENTICATED - SECURITY FIX)
    Route::get('divisi', [SettingController::class, 'getdivisi']);
    Route::get('region', [SettingController::class, 'getregion']);
});

// Route::post('user/register', [UserController::class, 'register']);
Route::post('user/login', [UserController::class, 'login'])->middleware('throttle:login');

Route::post('notif', [SendNotif::class, 'sendMessage']);

Route::post('test-upload', function (Illuminate\Http\Request $request, App\Services\FileUploadService $fileUpload) {
    $key = sprintf('test-upload:%s', $request->user()?->id ?? $request->ip());

    $allowed = Illuminate\Support\Facades\RateLimiter::attempt(
        $key,
        25,
        function () use ($request, $fileUpload) {
            if ($request->hasFile('file')) {
                $fileUpload->uploadImageOptimized(
                    $request->file('file'),
                    $request->input('type', 'photo')
                );
            }

            return true;
        },
        60
    );

    if (! $allowed) {
        $retryAfter = Illuminate\Support\Facades\RateLimiter::availableIn($key);

        return response()->json([
            'meta' => [
                'code' => 429,
                'status' => 'error',
                'message' => 'Rate limit exceeded',
            ],
            'data' => [
                'retry_after' => $retryAfter,
            ],
            'errors' => null,
        ], 429);
    }

    return response()->json([
        'meta' => [
            'code' => 200,
            'status' => 'success',
            'message' => 'Upload accepted',
        ],
        'data' => null,
        'errors' => null,
    ]);
});
