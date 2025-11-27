<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\API\Traits\HasMediaUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\CheckinVisitRequest;
use App\Http\Requests\API\CheckoutVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Outlet;
use App\Models\Visit;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VisitController extends Controller
{
    use HasMediaUpload;

    public function __construct(protected FileUploadService $fileUpload) {}

    public function monitor(Request $request)
    {
        try {
            $user = Auth::user();
            $compact = $request->boolean('compact', true);

            // Validate access
            if (! $user || ! $user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            if (! $scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $date = $request->date ? Carbon::parse($request->date)->toDateString() : now()->toDateString();

            $baseRelations = $compact
                ? [
                    'outlet:id,kode_outlet,nama_outlet',
                    'user:id,nama_lengkap,role_id',
                ]
                : [
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanUsahas',
                    'user.regions',
                    'user.divisis',
                    'user.clusters',
                    'user.role',
                ];

            // Get user's organizational assignments
            $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
            $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
            $regionIds = $user->regions()->pluck('regions.id')->toArray();
            $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

            // Check if user has assignments (if not full access)
            if ($scopeLevel !== 'all' && ! $user->role->hasFullAccess()) {
                $hasAnyAssignment = ! empty($badanUsahaIds) || ! empty($divisiIds) || ! empty($regionIds) || ! empty($clusterIds);

                if (! $hasAnyAssignment) {
                    return response()->json([
                        'meta' => [
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'fetch monitoring visit success',
                        ],
                        'data' => [],
                        'errors' => null,
                    ]);
                }
            }

            // Build query with scope-based filtering
            $visit = Visit::with($baseRelations)->whereDate('tanggal_visit', $date);

            if ($compact) {
                $visit->select([
                    'id',
                    'user_id',
                    'outlet_id',
                    'tanggal_visit',
                    'tipe_visit',
                    'check_in_time',
                    'check_out_time',
                    'transaksi',
                    'created_at',
                ]);
            }

            // Apply scope-level filtering
            if ($scopeLevel === 'all' || $user->role->hasFullAccess()) {
                // Full access - no filtering
                $visit = $visit->latest()->get();
            } else {
                // Apply organizational filters based on scope level
                $visit->where(function ($query) use ($scopeLevel, $badanUsahaIds, $divisiIds, $regionIds, $clusterIds) {
                    if ($scopeLevel === 'badanusaha') {
                        if (! empty($badanUsahaIds)) {
                            $query->whereHas('user.badanUsahas', fn ($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                        }

                        return;
                    }

                    if ($scopeLevel === 'divisi') {
                        if (! empty($badanUsahaIds)) {
                            $query->whereHas('user.badanUsahas', fn ($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                        }
                        if (! empty($divisiIds)) {
                            $query->whereHas('user.divisis', fn ($q) => $q->whereIn('divisions.id', $divisiIds));
                        }

                        return;
                    }

                    if ($scopeLevel === 'region') {
                        if (! empty($badanUsahaIds)) {
                            $query->whereHas('user.badanUsahas', fn ($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                        }
                        if (! empty($divisiIds)) {
                            $query->whereHas('user.divisis', fn ($q) => $q->whereIn('divisions.id', $divisiIds));
                        }
                        if (! empty($regionIds)) {
                            $query->whereHas('user.regions', fn ($q) => $q->whereIn('regions.id', $regionIds));
                        }

                        return;
                    }

                    // cluster level - apply all levels when provided
                    if (! empty($badanUsahaIds)) {
                        $query->whereHas('user.badanUsahas', fn ($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                    }
                    if (! empty($divisiIds)) {
                        $query->whereHas('user.divisis', fn ($q) => $q->whereIn('divisions.id', $divisiIds));
                    }
                    if (! empty($regionIds)) {
                        $query->whereHas('user.regions', fn ($q) => $q->whereIn('regions.id', $regionIds));
                    }
                    if (! empty($clusterIds)) {
                        $query->whereHas('user.clusters', fn ($q) => $q->whereIn('clusters.id', $clusterIds));
                    }
                });

                $visit = $visit->latest()->get();
            }

            return VisitResource::collection($visit)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'fetch monitoring visit success',
                ],
                'errors' => null,
            ]);
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err->getMessage(),
            ], $err->getMessage(), 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $compact = $request->boolean('compact', true);

            $query = Visit::with($compact ? [
                'outlet:id,kode_outlet,nama_outlet',
                'user:id,nama_lengkap,role_id',
            ] : [
                'outlet.badanusaha',
                'outlet.region',
                'outlet.divisi',
                'outlet.cluster',
                'user.badanUsahas',
                'user.regions',
                'user.divisis',
                'user.clusters',
                'user.role',
            ])->where('user_id', Auth::user()->id);

            if ($compact) {
                $query->select([
                    'id',
                    'user_id',
                    'outlet_id',
                    'tanggal_visit',
                    'tipe_visit',
                    'check_in_time',
                    'check_out_time',
                    'transaksi',
                    'durasi_visit',
                    'created_at',
                ]);
            }

            // Apply date filtering
            // Priority: custom range (date_from & date_to) > period
            if ($request->filled(['date_from', 'date_to'])) {
                // Custom date range
                $dateFrom = Carbon::parse($request->date_from)->startOfDay();
                $dateTo = Carbon::parse($request->date_to)->endOfDay();
                $query->whereBetween('tanggal_visit', [$dateFrom, $dateTo]);
            } else {
                // Period-based filtering
                $period = $request->input('period', 'today');

                switch ($period) {
                    case 'week':
                        // Current week (Monday to Sunday)
                        $query->whereBetween('tanggal_visit', [
                            Carbon::now()->startOfWeek(),
                            Carbon::now()->endOfWeek(),
                        ]);
                        break;

                    case 'month':
                        // Current month
                        $query->whereBetween('tanggal_visit', [
                            Carbon::now()->startOfMonth(),
                            Carbon::now()->endOfMonth(),
                        ]);
                        break;

                    case 'today':
                    default:
                        // Today only (default behavior)
                        $query->whereDate('tanggal_visit', date('Y-m-d'));
                        break;
                }
            }

            $visit = $query->latest()->get();

            return VisitResource::collection($visit)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'fetch visit succes',
                ],
                'errors' => null,
            ]);
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err->getMessage(),
            ], 'ERROR', 500);
        }
    }

    public function checkin(CheckinVisitRequest $request)
    {
        $temporaryFiles = [];
        $mediaQueue = [];
        $mediaDispatched = false;

        try {
            $user = Auth::user();

            // Validate no active visit
            $activeVisit = Visit::where('user_id', $user->id)
                ->whereDate('tanggal_visit', today())
                ->whereNull('check_out_time')
                ->first();

            if ($activeVisit) {
                return ResponseFormatter::error(
                    ['active_visit_id' => $activeVisit->id],
                    "Belum check out dari outlet {$activeVisit->outlet->kode_outlet}",
                    400
                );
            }

            // Find outlet with organizational filtering
            $outlet = Outlet::visibleTo($user)
                ->where('id', $request->outlet_id)
                ->first();

            if (! $outlet) {
                Log::channel('visit')->warning('Visit check-in failed: outlet not found', [
                    'user_id' => $user->id,
                    'outlet_id' => $request->outlet_id,
                ]);

                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
            $imageName = date('Y-m-d').'-'.$user->username.'-IN-'.Carbon::now()->getPreciseTimestamp(3).'.'.$ext;

            $temporaryPath = $this->fileUpload->storeTemporary(
                $request->file('picture_visit'),
                'tmp',
                ['filename' => $imageName]
            );
            $temporaryFiles[] = $temporaryPath;
            $mediaQueue[] = [
                'field' => 'picture_visit_in',
                'tmp_path' => $temporaryPath,
                'type' => 'visit-in',
                'filename' => $imageName,
            ];

            // Create visit
            $visit = Visit::create([
                'tanggal_visit' => today(),
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'tipe_visit' => $request->tipe_visit,
                'latlong_in' => $request->latlong_in,
                'check_in_time' => now(),
                'picture_visit_in' => $temporaryPath,
            ]);

            // Process media
            if ($mediaQueue !== []) {
                $mediaDispatched = $this->dispatchMediaJob('visit', $visit->id, $mediaQueue);
                $visit->refresh();
            }

            Log::channel('visit')->info('Visit check-in success', [
                'visit_id' => $visit->id,
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
            ]);

            return response()->json([
                'meta' => ['code' => 201, 'status' => 'success', 'message' => 'Check-in berhasil'],
                'data' => new VisitResource($visit),
                'errors' => null,
            ], 201);
        } catch (RuntimeException $e) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            return ResponseFormatter::error(['error' => $e->getMessage()], 'INVALID_FILE', 422);
        } catch (Exception $error) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            Log::channel('visit')->error('Visit check-in failed', [
                'user_id' => $user->id ?? null,
                'error' => $error->getMessage(),
            ]);

            return ResponseFormatter::error(['error' => 'Terjadi kesalahan saat check-in'], 'ERROR', 500);
        }
    }

    public function checkout(CheckoutVisitRequest $request, int $id)
    {
        $temporaryFiles = [];
        $mediaQueue = [];
        $mediaDispatched = false;

        try {
            $user = Auth::user();

            // Get visit
            $visit = Visit::where('id', $id)
                ->where('user_id', $user->id)
                ->whereNull('check_out_time')
                ->first();

            if (! $visit) {
                return ResponseFormatter::error(null, 'Visit tidak ditemukan atau sudah check-out', 404);
            }

            $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
            $imageName = date('Y-m-d').'-'.$user->username.'-OUT-'.Carbon::now()->getPreciseTimestamp(3).'.'.$ext;

            $temporaryPath = $this->fileUpload->storeTemporary(
                $request->file('picture_visit'),
                'tmp',
                ['filename' => $imageName]
            );
            $temporaryFiles[] = $temporaryPath;
            $mediaQueue[] = [
                'field' => 'picture_visit_out',
                'tmp_path' => $temporaryPath,
                'type' => 'visit-out',
                'filename' => $imageName,
            ];

            // Calculate duration
            $checkInTime = Carbon::parse($visit->check_in_time);
            $checkOutTime = now();
            $duration = $checkOutTime->diffInMinutes($checkInTime);

            // Update visit
            $visit->forceFill([
                'latlong_out' => $request->latlong_out,
                'check_out_time' => $checkOutTime,
                'laporan_visit' => $request->laporan_visit,
                'transaksi' => $request->transaksi,
                'durasi_visit' => $duration,
                'picture_visit_out' => $temporaryPath,
            ])->save();

            // Process media
            if ($mediaQueue !== []) {
                $mediaDispatched = $this->dispatchMediaJob('visit', $visit->id, $mediaQueue);
                $visit->refresh();
            }

            Log::channel('visit')->info('Visit check-out success', [
                'visit_id' => $visit->id,
                'user_id' => $user->id,
                'durasi' => $duration.' minutes',
            ]);

            return response()->json([
                'meta' => ['code' => 200, 'status' => 'success', 'message' => 'Check-out berhasil'],
                'data' => new VisitResource($visit),
                'errors' => null,
            ]);
        } catch (RuntimeException $e) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            return ResponseFormatter::error(['error' => $e->getMessage()], 'INVALID_FILE', 422);
        } catch (Exception $error) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            Log::channel('visit')->error('Visit check-out failed', [
                'user_id' => $user->id ?? null,
                'visit_id' => $id,
                'error' => $error->getMessage(),
            ]);

            return ResponseFormatter::error(['error' => 'Terjadi kesalahan saat check-out'], 'ERROR', 500);
        }
    }

    private function cleanupTemporaryFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $disk = Storage::disk($this->fileUpload->temporaryDisk());

        foreach ($paths as $path) {
            if (! $path) {
                continue;
            }

            $disk->delete($path);
        }
    }

    protected function getPhotoFieldMapping(string $modelType): array
    {
        if ($modelType === 'visit') {
            return [
                'photo0' => 'picture_visit_in',
                'photo1' => 'picture_visit_out',
            ];
        }

        return [];
    }
}
