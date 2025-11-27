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
Route::post('login', [UserController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'logku'])->group(function () {
    // USER
    Route::get('user', [UserController::class, 'fetch']);
    Route::post('user', [UserController::class, 'store']);
    Route::post('logout', [UserController::class, 'logout']);

    // OUTLET
    Route::get('outlet', [OutletController::class, 'fetch']);
    Route::get('outlet/{id}', [OutletController::class, 'show']);
    Route::post('outlet/{id}', [OutletController::class, 'update']);

    // VISIT
    Route::get('visit', [VisitController::class, 'fetch']);
    Route::post('visit/checkin', [VisitController::class, 'checkin']);
    Route::post('visit/{id}/checkout', [VisitController::class, 'checkout']);
    Route::get('visit/monitor', [VisitController::class, 'monitor']);

    // PLANVISIT
    Route::get('planvisit', [PlanVisitController::class, 'fetch']);
    Route::post('planvisit', [PlanVisitController::class, 'store']);
    Route::delete('planvisit', [PlanVisitController::class, 'delete']);

    // REGISTERS Resource (NOO & LEAD workflow)
    Route::prefix('registers')->group(function () {
        // List & Show
        Route::get('/', [RegisterController::class, 'fetch']);              // User's registers (backwards compatible with /noo)
        Route::get('/all', [RegisterController::class, 'all']);             // Organizational scope
        Route::get('/pending', [RegisterController::class, 'getRegisterOutlet']); // Pending approval (backwards compatible with /nooOutlet)
        Route::get('/{id}', [RegisterController::class, 'show']);           // Show single register

        // Create - Type-specific endpoints
        Route::post('/leads', [RegisterController::class, 'submitLead']);   // Create LEAD (without KTP)
        Route::post('/noos', [RegisterController::class, 'submitNoo']);     // Create NOO (with KTP)

        // State Transitions - RESTful sub-resources
        Route::patch('/{id}/upgrade', [RegisterController::class, 'upgradeLead']);  // LEAD → NOO (upload KTP)
        Route::patch('/{id}/confirm', [RegisterController::class, 'confirmNoo']);   // Confirm NOO (set kode_outlet & limit)
        Route::patch('/{id}/approve', [RegisterController::class, 'approveNoo']);   // Approve → Outlet
        Route::patch('/{id}/reject', [RegisterController::class, 'rejectNoo']);     // Reject NOO
    });

    // MASTER DATA - Organization Hierarchy (with role-based filtering)
    Route::get('badanusaha', [SettingController::class, 'getbadanusaha']);
    Route::get('divisi', [SettingController::class, 'getdivisi']);
    Route::get('region', [SettingController::class, 'getregion']);
    Route::get('cluster', [SettingController::class, 'getcluster']);
    Route::get('form-options', [SettingController::class, 'getFormOptions']);
    Route::get('roles', [SettingController::class, 'getRoleOptions']);
});

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
