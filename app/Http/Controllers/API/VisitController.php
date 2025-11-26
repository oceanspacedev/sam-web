<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\API\Traits\HasMediaUpload;
use App\Http\Controllers\Controller;
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

    public function __construct(protected FileUploadService $fileUpload)
    {
    }

    /**
     * Retrieve role-based visit monitoring data
     *
     * Returns visit records filtered by organizational scope level.
     * Uses dynamic filtering based on user's organizational assignments.
     *
     * @queryParam date string optional Filter visits by specific date. Format: YYYY-MM-DD. Example: "2024-01-15"
     */
    public function monitor(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate access
            if (!$user || !$user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            if (!$scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $date = $request->date ? Carbon::parse($request->date)->toDateString() : now()->toDateString();

            $baseRelations = [
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
            if ($scopeLevel !== 'all' && !$user->role->hasFullAccess()) {
                $hasAnyAssignment = !empty($badanUsahaIds) || !empty($divisiIds) || !empty($regionIds) || !empty($clusterIds);

                if (!$hasAnyAssignment) {
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

            // Apply scope-level filtering
            if ($scopeLevel === 'all' || $user->role->hasFullAccess()) {
                // Full access - no filtering
                $visit = $visit->latest()->get();
            } else {
                // Apply organizational filters based on scope level
                $visit->where(function ($query) use ($scopeLevel, $badanUsahaIds, $divisiIds, $regionIds, $clusterIds) {
                    if ($scopeLevel === 'badanusaha') {
                        if (!empty($badanUsahaIds)) {
                            $query->whereHas('user.badanUsahas', fn($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                        }
                        return;
                    }

                    if ($scopeLevel === 'divisi') {
                        if (!empty($badanUsahaIds)) {
                            $query->whereHas('user.badanUsahas', fn($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                        }
                        if (!empty($divisiIds)) {
                            $query->whereHas('user.divisis', fn($q) => $q->whereIn('divisions.id', $divisiIds));
                        }
                        return;
                    }

                    if ($scopeLevel === 'region') {
                        if (!empty($badanUsahaIds)) {
                            $query->whereHas('user.badanUsahas', fn($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                        }
                        if (!empty($divisiIds)) {
                            $query->whereHas('user.divisis', fn($q) => $q->whereIn('divisions.id', $divisiIds));
                        }
                        if (!empty($regionIds)) {
                            $query->whereHas('user.regions', fn($q) => $q->whereIn('regions.id', $regionIds));
                        }
                        return;
                    }

                    // cluster level - apply all levels when provided
                    if (!empty($badanUsahaIds)) {
                        $query->whereHas('user.badanUsahas', fn($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                    }
                    if (!empty($divisiIds)) {
                        $query->whereHas('user.divisis', fn($q) => $q->whereIn('divisions.id', $divisiIds));
                    }
                    if (!empty($regionIds)) {
                        $query->whereHas('user.regions', fn($q) => $q->whereIn('regions.id', $regionIds));
                    }
                    if (!empty($clusterIds)) {
                        $query->whereHas('user.clusters', fn($q) => $q->whereIn('clusters.id', $clusterIds));
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

    /**
     * Retrieve authenticated user's visits for today
     *
     * Returns all visit records for the currently authenticated user,
     * automatically filtered for today's date. Includes complete relationship
     * data for outlet, user, and organizational hierarchy.
     *
     * @response array{
     *   data: array{
     *     id: int,
     *     tanggal_visit: int,
     *     user_id: int,
     *     outlet_id: int,
     *     tipe_visit: string,
     *     latlong_in: string,
     *     latlong_out: string,
     *     check_in_time: int,
     *     check_out_time: int|null,
     *     laporan_visit: string,
     *     durasi_visit: int|null,
     *     picture_visit_in: string,
     *     picture_visit_out: string,
     *     outlet: object,
     *     user: object,
     *     transaksi: string
     *   }[],
     *   message: string
     * }
     * @response 500 array{
     *   data: array{
     *     message: string
     *   },
     *   message: string
     * }
     */
    public function fetch(Request $request)
    {
        try {
            $visit = Visit::with([
                'outlet.badanusaha',
                'outlet.region',
                'outlet.divisi',
                'outlet.cluster',
                'user.badanUsahas',
                'user.regions',
                'user.divisis',
                'user.clusters',
                'user.role',
            ])
                ->where('user_id', Auth::user()->id)
                ->whereDate('tanggal_visit', date('Y-m-d'))
                ->latest()
                ->get();

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
                'message' => $err,
            ], $err, 500);
        }
    }

    /**
     * Check-in to outlet
     * 
     * Creates a new visit record with check-in photo and coordinates.
     * Validates that user doesn't have an active (unchecked-out) visit today.
     *
     * @bodyParam outlet_id int required Outlet ID. Example: 123
     * @bodyParam picture_visit file required Check-in photo. Max 3MB
     * @bodyParam latlong_in string required Check-in coordinates. Example: "-6.2,106.8"
     * @bodyParam tipe_visit string required Visit type. Example: "routine"
     */
    public function checkin(Request $request)
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

            // Validate request
            $request->validate([
                'outlet_id' => 'required|integer',
                'picture_visit' => 'required|file|image|mimes:jpg,jpeg,png|max:3072',
                'latlong_in' => 'required|string',
                'tipe_visit' => 'required',
            ]);

            // Find outlet (with role-based filtering)
            $outletQuery = Outlet::where('id', $request->outlet_id);
            $scopeLevel = $user->role->organizational_scope_level;

            if ($scopeLevel !== 'all' && !$user->role->hasFullAccess()) {
                if ($scopeLevel === 'badanusaha') {
                    $userBuIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                    if (!empty($userBuIds)) {
                        $outletQuery->whereIn('badan_usaha_id', $userBuIds);
                    } else {
                        return ResponseFormatter::error(null, 'Outlet tidak ditemukan (No BU assigned)', 404);
                    }
                } elseif ($scopeLevel === 'divisi') {
                    $userDivisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                    if (!empty($userDivisiIds)) {
                        $outletQuery->whereIn('divisi_id', $userDivisiIds);
                    } else {
                        return ResponseFormatter::error(null, 'Outlet tidak ditemukan (No division assigned)', 404);
                    }
                } elseif ($scopeLevel === 'region') {
                    $userRegionIds = $user->regions()->pluck('regions.id')->toArray();
                    if (!empty($userRegionIds)) {
                        $outletQuery->whereIn('region_id', $userRegionIds);
                    } else {
                        return ResponseFormatter::error(null, 'Outlet tidak ditemukan (No region assigned)', 404);
                    }
                } elseif ($scopeLevel === 'cluster') {
                    $userClusterIds = $user->clusters()->pluck('clusters.id')->toArray();
                    if (!empty($userClusterIds)) {
                        $outletQuery->whereIn('cluster_id', $userClusterIds);
                    } else {
                        return ResponseFormatter::error(null, 'Outlet tidak ditemukan (No cluster assigned)', 404);
                    }
                }
            }

            $outlet = $outletQuery->first();

            if (!$outlet) {
                Log::channel('visit')->warning('Visit check-in failed: outlet not found', [
                    'user_id' => $user->id,
                    'outlet_id' => $request->outlet_id,
                ]);
                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            // Validate and store photo
            if (!$request->hasFile('picture_visit') || !$request->file('picture_visit')->isValid()) {
                return ResponseFormatter::error('File gambar tidak valid', 'INVALID_FILE', 422);
            }

            $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
            $imageName = date('Y-m-d') . '-' . $user->username . '-IN-' . Carbon::now()->getPreciseTimestamp(3) . '.' . $ext;

            try {
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
            } catch (RuntimeException $e) {
                $this->cleanupTemporaryFiles($temporaryFiles);
                return ResponseFormatter::error($e->getMessage(), 'INVALID_FILE', 422);
            }

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
        } catch (Exception $error) {
            if (!$mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }
            return ResponseFormatter::error(['error' => $error->getMessage()], 'error', 500);
        }
    }

    /**
     * Check-out from visit
     * 
     * Updates visit with check-out photo, coordinates, report, and calculates duration.
     *
     * @param int $id Visit ID
     * @bodyParam latlong_out string required Check-out coordinates
     * @bodyParam laporan_visit string required Visit report
     * @bodyParam picture_visit file required Check-out photo. Max 3MB
     * @bodyParam transaksi string required Transaction info
     */
    public function checkout(Request $request, int $id)
    {
        $temporaryFiles = [];
        $mediaQueue = [];
        $mediaDispatched = false;

        try {
            $user = Auth::user();

            // Validate request
            $request->validate([
                'latlong_out' => 'required',
                'laporan_visit' => 'required',
                'picture_visit' => 'required|file|image|mimes:jpg,jpeg,png|max:3072',
                'transaksi' => 'required',
            ]);

            // Get visit
            $visit = Visit::where('id', $id)
                ->where('user_id', $user->id)
                ->whereNull('check_out_time')
                ->first();

            if (!$visit) {
                return ResponseFormatter::error(null, 'Visit tidak ditemukan atau sudah check-out', 404);
            }

            // Validate and store photo
            if (!$request->hasFile('picture_visit') || !$request->file('picture_visit')->isValid()) {
                return ResponseFormatter::error('File gambar tidak valid', 'INVALID_FILE', 422);
            }

            $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
            $imageName = date('Y-m-d') . '-' . $user->username . '-OUT-' . Carbon::now()->getPreciseTimestamp(3) . '.' . $ext;

            try {
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
            } catch (RuntimeException $e) {
                $this->cleanupTemporaryFiles($temporaryFiles);
                return ResponseFormatter::error($e->getMessage(), 'INVALID_FILE', 422);
            }

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
                'durasi' => $duration . ' minutes',
            ]);

            return response()->json([
                'meta' => ['code' => 200, 'status' => 'success', 'message' => 'Check-out berhasil'],
                'data' => new VisitResource($visit),
                'errors' => null,
            ]);
        } catch (Exception $error) {
            if (!$mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }
            return ResponseFormatter::error(['error' => $error->getMessage()], 'error', 500);
        }
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    private function cleanupTemporaryFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $disk = Storage::disk($this->fileUpload->temporaryDisk());

        foreach ($paths as $path) {
            if (!$path) {
                continue;
            }

            $disk->delete($path);
        }
    }

    /**
     * Get photo field mapping for visit model
     * Used by HasMediaUpload trait
     */
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
