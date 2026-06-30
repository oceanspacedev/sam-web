<?php

use App\Helpers\SendNotif;
use App\Http\Controllers\API\Management\BadanUsahaController as ManagementBadanUsahaController;
use App\Http\Controllers\API\Management\ClusterController as ManagementClusterController;
use App\Http\Controllers\API\Management\DivisionController as ManagementDivisionController;
use App\Http\Controllers\API\Management\RegionController as ManagementRegionController;
use App\Http\Controllers\API\OutletController;
use App\Http\Controllers\API\PlanVisitController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\VisitController;
use App\Http\Controllers\API\WhatsAppAuthController;
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
Route::post('login/whatsapp/request-otp', [WhatsAppAuthController::class, 'requestLoginOtp'])->middleware('throttle:whatsapp-otp');
Route::post('login/whatsapp/verify-otp', [WhatsAppAuthController::class, 'verifyLoginOtp'])->middleware('throttle:whatsapp-verify');

Route::middleware(['auth:sanctum', 'logku'])->group(function () {
    // USER (single - current user)
    Route::get('user', [UserController::class, 'fetch']);
    Route::put('user', [UserController::class, 'updateProfile']);
    Route::post('user/photo', [UserController::class, 'updateProfilePhoto']);
    Route::post('user/whatsapp/request-otp', [WhatsAppAuthController::class, 'requestProfileOtp'])->middleware('throttle:whatsapp-otp');
    Route::post('user/whatsapp/verify-otp', [WhatsAppAuthController::class, 'verifyProfileOtp'])->middleware('throttle:whatsapp-verify');
    Route::delete('user', [UserController::class, 'deleteAccount']);
    Route::get('user/stats', [UserController::class, 'stats']);
    Route::post('logout', [UserController::class, 'logout']);

    // USERS (manage all users - CRUD)
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    // OUTLET
    Route::get('outlet', [OutletController::class, 'fetch']);
    Route::get('outlet/{id}', [OutletController::class, 'show']);
    Route::get('outlet/{id}/archives', [OutletController::class, 'archives']);
    Route::patch('outlet/{id}/archives/{archiveId}/restore', [OutletController::class, 'restoreArchive']);
    Route::post('outlet/{id}', [OutletController::class, 'update']);
    Route::patch('outlet/{id}/reset', [OutletController::class, 'reset']);
    Route::patch('outlet/{id}/reset-location', [OutletController::class, 'resetLocation']);
    Route::delete('outlet/{id}', [OutletController::class, 'destroy']);

    // VISIT
    Route::get('visit', [VisitController::class, 'fetch']);
    Route::get('visit/targets', [VisitController::class, 'getTargets']);
    Route::post('visit/checkin', [VisitController::class, 'checkin']);
    Route::post('visit/{id}/checkout', [VisitController::class, 'checkout']);
    Route::get('visit/monitor', [VisitController::class, 'monitor']);
    Route::get('visit/{id}', [VisitController::class, 'show'])->whereNumber('id');

    // PLANVISIT
    Route::get('planvisit', [PlanVisitController::class, 'fetch']);
    Route::post('planvisit', [PlanVisitController::class, 'store']);
    Route::delete('planvisit/{id}', [PlanVisitController::class, 'destroy'])->whereNumber('id');

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

    // ORGANIZATION MANAGEMENT - Mobile CRUD (permission + scope gated)
    Route::prefix('management')->group(function () {
        Route::get('badanusaha', [ManagementBadanUsahaController::class, 'index']);
        Route::get('badanusaha/{id}', [ManagementBadanUsahaController::class, 'show'])->whereNumber('id');
        Route::post('badanusaha', [ManagementBadanUsahaController::class, 'store']);
        Route::put('badanusaha/{id}', [ManagementBadanUsahaController::class, 'update'])->whereNumber('id');
        Route::delete('badanusaha/{id}', [ManagementBadanUsahaController::class, 'destroy'])->whereNumber('id');

        Route::get('divisi', [ManagementDivisionController::class, 'index']);
        Route::get('divisi/{id}', [ManagementDivisionController::class, 'show'])->whereNumber('id');
        Route::post('divisi', [ManagementDivisionController::class, 'store']);
        Route::put('divisi/{id}', [ManagementDivisionController::class, 'update'])->whereNumber('id');
        Route::delete('divisi/{id}', [ManagementDivisionController::class, 'destroy'])->whereNumber('id');

        Route::get('region', [ManagementRegionController::class, 'index']);
        Route::get('region/{id}', [ManagementRegionController::class, 'show'])->whereNumber('id');
        Route::post('region', [ManagementRegionController::class, 'store']);
        Route::put('region/{id}', [ManagementRegionController::class, 'update'])->whereNumber('id');
        Route::delete('region/{id}', [ManagementRegionController::class, 'destroy'])->whereNumber('id');

        Route::get('cluster', [ManagementClusterController::class, 'index']);
        Route::get('cluster/{id}', [ManagementClusterController::class, 'show'])->whereNumber('id');
        Route::post('cluster', [ManagementClusterController::class, 'store']);
        Route::put('cluster/{id}', [ManagementClusterController::class, 'update'])->whereNumber('id');
        Route::delete('cluster/{id}', [ManagementClusterController::class, 'destroy'])->whereNumber('id');
    });

    // Custom Register Fields
    Route::get('divisions/{id}/register-fields', [RegisterController::class, 'getRegisterFields']);
});

Route::post('notif', [SendNotif::class, 'sendMessage']);

if (app()->environment(['local', 'testing'])) {
    Route::post('test-upload', function (Illuminate\Http\Request $request, App\Services\FileUploadService $fileUpload) {
        $incrementCounter = function (string $key, int $ttlSeconds): int {
            try {
                $count = (int) Illuminate\Support\Facades\Redis::incr($key);

                if ($count === 1) {
                    Illuminate\Support\Facades\Redis::expire($key, $ttlSeconds);
                }

                return $count;
            } catch (Throwable) {
                if (! Illuminate\Support\Facades\Cache::has($key)) {
                    Illuminate\Support\Facades\Cache::put($key, 1, now()->addSeconds($ttlSeconds));

                    return 1;
                }

                $count = ((int) Illuminate\Support\Facades\Cache::get($key, 0)) + 1;
                Illuminate\Support\Facades\Cache::put($key, $count, now()->addSeconds($ttlSeconds));

                return $count;
            }
        };

        $counterTtl = function (string $key, int $fallbackSeconds): int {
            try {
                $ttl = (int) Illuminate\Support\Facades\Redis::ttl($key);

                return $ttl > 0 ? $ttl : $fallbackSeconds;
            } catch (Throwable) {
                return $fallbackSeconds;
            }
        };

        $ipAddress = $request->ip();
        $userId = auth('sanctum')->id() ?? auth()->id() ?? $request->user()?->id;
        $limitPerMinute = $userId ? 60 : 40;
        $limitPerHour = $userId ? 300 : 120;

        $minuteKey = 'upload:minute:'.$ipAddress.':'.($userId ?? 'guest');
        $minuteCount = $incrementCounter($minuteKey, 60);

        if ($minuteCount > $limitPerMinute) {
            return response()->json([
                'meta' => [
                    'code' => 429,
                    'status' => 'error',
                    'message' => 'Too many upload attempts. Please try again later.',
                ],
                'data' => [
                    'retry_after' => 60,
                    'limit' => $limitPerMinute,
                ],
                'errors' => null,
            ], 429);
        }

        $hourKey = 'upload:hour:'.$ipAddress.':'.($userId ?? 'guest');
        $hourCount = $incrementCounter($hourKey, 3600);

        if ($hourCount > $limitPerHour) {
            return response()->json([
                'meta' => [
                    'code' => 429,
                    'status' => 'error',
                    'message' => 'Hourly upload limit exceeded. Please try again later.',
                ],
                'data' => [
                    'retry_after' => 3600,
                    'limit' => $limitPerHour,
                ],
                'errors' => null,
            ], 429);
        }

        if ($request->hasFile('file')) {
            $fileUpload->uploadImageOptimized(
                $request->file('file'),
                $request->input('type', 'photo')
            );
        }

        $response = response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Upload accepted',
            ],
            'data' => null,
            'errors' => null,
        ]);

        $response->headers->set('X-Upload-Limit-Remaining', max(0, $limitPerMinute - $minuteCount));
        $response->headers->set('X-Upload-Limit-Reset', $counterTtl($minuteKey, 60));

        return $response;
    });
}
