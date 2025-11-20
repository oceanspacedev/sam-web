<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\API\Traits\HasMediaUpload;
use App\Http\Controllers\Controller;
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

    /**
     * Retrieve role-based visit monitoring data
     *
     * Returns visit records filtered by user role with specific access patterns.
     * Includes detailed relationship data for outlet, user, and organizational hierarchies.
     * Supports special user cases with custom filtering rules.
     *
     * **Special user access patterns:**
     * - **Robby (GM ZTE)**: User ID 2, Role ID 8 - Filters ZTE division visits (divisi_id: 8) in specific regions
     * - **Hendra Setia (GM Techno)**: User ID 689, Role ID 8 - Filters Techno division visits (divisi_id: 11)
     *
     * **Role-based filtering:**
     * - **ASM/RKAM (role_id: 1, 9)**: Monitors users under their TM hierarchy
     * - **COO (role_id: 6)**: Global access to all visits
     * - **CSO (role_id: 8)**: Limited to division_id: 4 visits
     * - **CSO FAST EV (role_id: 11)**: Limited to division_id: 7 visits
     * - **Default**: Region-based filtering matching user's region
     *
     * @queryParam date string optional Filter visits by specific date. Format: YYYY-MM-DD. Example: "2024-01-15"
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
    public function monitor(Request $request)
    {
        try {

            $user = Auth::user();
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

            $scopeLevel = $user->role?->organizational_scope_level ?? 'cluster';
            $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
            $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
            $regionIds = $user->regions()->pluck('regions.id')->toArray();
            $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();
            // Robby (GM ZTE)
            if ($user->id == 2 && $user->role_id == 8) {
                $visit = Visit::with($baseRelations)
                    ->whereHas('user.divisis', fn ($query) => $query->where('divisions.id', 8))
                    ->whereHas('user.regions', fn ($query) => $query->whereIn('regions.id', [63, 64, 66, 67, 68, 78, 79, 80, 81]))
                    ->whereDate('tanggal_visit', $date)
                    ->latest()
                    ->get();

                // VisitNoo removed; only include regular visits
            }
            // Hendra Setia (GM Techno)
            elseif ($user->id == 689 && $user->role_id == 8) {
                $visit = Visit::with($baseRelations)
                    ->whereHas('user.divisis', fn ($query) => $query->where('divisions.id', 11))
                    ->whereDate('tanggal_visit', $date)
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            // ASM || RKAM
            elseif ($user->role_id == 1 || $user->role_id == 9) {
                $visit = Visit::with($baseRelations)
                    ->whereHas('user', function ($query) use ($user) {
                        $query->where('tm_id', $user->id);
                    })
                    ->whereDate('tanggal_visit', $date)
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            // COO
            elseif ($user->role_id == 6) {
                $visit = Visit::with($baseRelations)
                    ->whereDate('tanggal_visit', $date)
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            // CSO
            elseif ($user->role_id == 8) {
                $visit = Visit::with($baseRelations)
                    ->whereHas('outlet', function ($query) {
                        $query->where('divisi_id', 4);
                    })
                    ->whereDate('tanggal_visit', $date)
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            // CSO FAST EV
            elseif ($user->role_id == 11) {
                $visit = Visit::with($baseRelations)
                    ->whereHas('outlet', function ($query) {
                        $query->where('divisi_id', 7);
                    })
                    ->whereDate('tanggal_visit', $date)
                    ->latest()
                    ->get();

                // VisitNoo removed
            } else {
                $visit = Visit::with($baseRelations)
                    ->whereDate('tanggal_visit', $date);

                if ($user->role && $user->role->hasFullAccess()) {
                    $visit = $visit->latest()->get();
                } else {
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

                        // cluster level (default) - apply all levels when provided
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

                // VisitNoo removed
            }

            return ResponseFormatter::success(
                $visit->map->formatForAPI(),
                'fetch monitoring visit success'
            );
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
            // Legacy frontends still request /visit?isnoo=1 for NOO flow.
            // Only when the flag is explicitly "1" do we short-circuit.
            if ($request->query('isnoo') === '1') {
                return ResponseFormatter::success([], 'NOO visit data is not available');
            }

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

            return ResponseFormatter::success(
                $visit->map->formatForAPI(),
                'fetch visit succes'
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], $err, 500);
        }
    }

    /**
     * Validate check-in/check-out eligibility for visits
     *
     * Validates whether a user can perform check-in or check-out operations
     * based on their current visit status. Ensures proper visit workflow by
     * preventing multiple check-ins and ensuring check-out sequence.
     *
     * **Check-in validation:**
     * - User must not have an active visit without check-out
     * - Prevents multiple active check-ins
     *
     * **Check-out validation:**
     * - Currently allows check-out without additional restrictions
     * - Future validation may include ensuring check-out follows check-in
     *
     * @bodyParam kode_outlet string required Outlet code to validate visit eligibility. Example: "OUTLET001"
     * @bodyParam check_in boolean optional Indicates if this is a check-in validation request. Example: true
     *
     * @response array{
     *   data: null,
     *   message: string
     * }
     * @response 400 array{
     *   data: array{
     *     message: string
     *   },
     *   message: string
     * }
     * @response 422 array{
     *   data: array{
     *     kode_outlet: string[]
     *   },
     *   message: string
     * }
     */
    public function check(Request $request)
    {
        $request->validate([
            'kode_outlet' => ['required'],
        ]);

        // #cek database terkahir dari tanggal sekarang dan user tersebut
        $lastDataVisit = Visit::whereDate('tanggal_visit', date('Y-m-d'))->where('user_id', Auth::user()->id)->latest()->first();
        // #cek data terkahir durasi

        // #cek table dengan outlet yang dikirim dan tanggal hari ini
        // $lastDataSelectedOutletVisit = Visit::with(['outlet.cluster','outlet.user.cluster'])->whereDate('tanggal_visit',date('Y-m-d'))->where('user_id',Auth::user()->id)->where('outlet_id',$outletId)->first();

        // #kalo mode ci
        if ($request->check_in) {
            // cek apa ada data terakhir kosong ?
            if ($lastDataVisit) {
                // if($lastDataSelectedOutletVisit)
                // {
                //     return ResponseFormatter::error(null,'anda sudah check in hari ini di outlet '. $lastDataSelectedOutletVisit->outlet->kode_outlet);
                // }
                // else
                if ($lastDataVisit->check_out_time) {
                    return ResponseFormatter::success(null, 'ok');
                } else {
                    // notif error
                    return ResponseFormatter::error([
                        'message' => 'error',
                    ], 'Belum check out dari outlet '.$lastDataVisit->outlet->kode_outlet, 400);
                }
            } else {
                return ResponseFormatter::success(null, 'ok');
            }
        }
        // #disini co
        else {
            // $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();
            // $outletId = $outlet->id;
            // if ($lastDataVisit) {
            //     if ($lastDataVisit->durasi_visit) {
            //         $isExistingLastDurasi = true;
            //     }
            //     $isExistingLastDurasi = false;
            // } else {
            //     $isExistingLastDurasi = false;
            // }
            // #kalau belum ada durasi maka bernilai true
            // if (!$isExistingLastDurasi) {
            //     // ##kalau ada histori outlet tersebut di hari ini maka bernilai true
            //     // if($lastDataSelectedOutletVisit)
            //     // {
            //     //     ##kalau outlet tersebut sudah ada durasi visit bernilai true
            //     //     if($lastDataSelectedOutletVisit->durasi_visit)
            //     //     {
            //     //         return ResponseFormatter::error([
            //     //                 'data' => 'anda sudah checkout'
            //     //                 ],'anda hari ini sudah check out di outlet '.$lastDataSelectedOutletVisit->outlet->kode_outlet,400);
            //     //     }
            //     // }
            //     ##cek data last visit hari ini bernilai true jika ada
            //     // else
            //     if ($lastDataVisit) {
            //         ##cek dari data terkahir visit apakah ada durasi visit dan sama outlet id nya dengan yang dikirim jika keduanya salah maka bernilai true
            //         if ($lastDataVisit->durasi_visit == null && $lastDataVisit->outlet_id != $outletId) {
            //             return ResponseFormatter::error([
            //                 'message' => 'error'
            //             ], "Belum check out dari outlet " . $lastDataVisit->outlet->kode_outlet, 400);
            //         } else {
            //             if ($lastDataVisit->outlet_id == $outletId) {
            //                 return ResponseFormatter::success(null, 'ok');
            //             }
            //             ##jika belum ci dimanapun
            //             return  ResponseFormatter::error([
            //                 'data' => 'anda belum checkin'
            //             ], 'anda belum check in di outlet manapun ', 400);
            //         }
            //     }
            // } else {
            //     ##jika belum ci dimanapun
            //     return  ResponseFormatter::error([
            //         'data' => 'anda belum checkin'
            //     ], 'anda belum check in di outlet manapun ', 400);
            // }
            return ResponseFormatter::success(null, 'ok');
        }
    }

    /**
     * Submit visit check-in or check-out with photo requirements
     *
     * Handles both check-in and check-out operations for visits with automatic
     * photo storage, duration calculation, and role-based outlet filtering.
     * Photos are stored with timestamp-based naming for easy identification.
     *
     * **Check-in Requirements:**
     * - DSF/DM and ASC roles can only check in to outlets matching their division
     * - Other roles have access to all outlets
     * - Requires check-in photo, coordinates, and visit type
     * - Photos stored using flat storage filenames (no nested directories) with format: YYYY-MM-DD-username-IN-timestamp.ext
     *
     * **Check-out Requirements:**
     * - Must have an existing check-in for today
     * - Calculates visit duration automatically
     * - Requires check-out photo, report, and transaction info
     * - Photos stored using flat storage filenames (no nested directories) with format: YYYY-MM-DD-username-OUT-timestamp.ext
     *
     * @bodyParam kode_outlet string required Outlet code for visit submission. Example: "OUTLET001"
     * @bodyParam picture_visit file required Visit photo (check-in or check-out). Max 3MB, formats: jpg,jpeg,png
     * @bodyParam latlong_in string required for check-in Check-in coordinates. Example: "-6.2088,106.8456"
     * @bodyParam latlong_out string required for check-out Check-out coordinates. Example: "-6.2088,106.8456"
     * @bodyParam tipe_visit string required for check-in Type of visit. Example: "routine"
     * @bodyParam laporan_visit string required for check-out Visit report summary. Example: "Successful sales visit"
     * @bodyParam transaksi string required for check-out Transaction information. Example: "Sold 5 units"
     *
     * @response array{
     *   data: array{
     *     visit: array{
     *       id: int,
     *       tanggal_visit: string,
     *       user_id: int,
     *       outlet_id: int,
     *       tipe_visit: string,
     *       latlong_in: string,
     *       check_in_time: string,
     *       picture_visit_in: string
     *     }|array{
     *       tanggal_visit: string,
     *       latlong_out: string,
     *       check_out_time: string,
     *       laporan_visit: string,
     *       durasi_visit: int,
     *       picture_visit_out: string,
     *       transaksi: string
     *     },
     *   },
     *   message: string
     * }
     * @response 422 array{
     *   data: string|array,
     *   message: string
     * }
     * @response 404 array{
     *   data: null,
     *   message: string
     * }
     * @response 500 array{
     *   data: array{
     *     error: object
     *   },
     *   message: string
     * }
     */
    public function submit(Request $request)
    {
        $temporaryFiles = [];
        $mediaQueue = [];
        $mediaDispatched = false;

        try {
            $checkIn = $request->latlong_in;
            $checkOut = $request->latlong_out;
            if ($checkIn) {
                $mediaQueue = [];
                $user = Auth::user();
                if ($user->role->name === 'DSF/DM' || $user->role->name === 'ASC') {
                    // Use many-to-many divisi relationship instead of direct divisi_id property
                    $userDivisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                    if (! empty($userDivisiIds)) {
                        $outlet = Outlet::where('kode_outlet', $request->kode_outlet)
                            ->whereIn('divisi_id', $userDivisiIds)
                            ->first();
                    } else {
                        $outlet = null; // No division assigned, cannot find outlet
                    }
                } else {
                    $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();
                }

                if (! $outlet) {
                    Log::channel('visit')->warning('Visit check-in failed: outlet not found', [
                        'user_id' => $user->id,
                        'kode_outlet' => $request->kode_outlet,
                    ]);

                    return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
                }

                $outletId = $outlet->id;
                $request->validate([
                    'kode_outlet' => ['required'],
                    'picture_visit' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
                    'latlong_in' => ['required', 'string'],
                    'tipe_visit' => ['required'],
                ]);
                if (! $request->hasFile('picture_visit') || ! $request->file('picture_visit')->isValid()) {
                    return ResponseFormatter::error('File gambar tidak valid', 'INVALID_FILE', 422);
                }
                $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
                $imageName = date('Y-m-d').'-'.Auth::user()->username.'-'.'IN-'.Carbon::now()->getPreciseTimestamp(3).'.'.$ext;
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
                        'type' => 'visit-in', // Use type instead of directory - TRUE FLAT STORAGE!
                        'filename' => $imageName,
                    ];
                } catch (RuntimeException $e) {
                    $this->cleanupTemporaryFiles($temporaryFiles);

                    return ResponseFormatter::error($e->getMessage(), 'INVALID_FILE', 422);
                }
                $user = Auth::user();

                // Log check-in request with payload info
                Log::channel('visit')->info('VisitController@submit request', [
                    'user_id' => $user->id,
                    'role_id' => $user->role_id,
                    'payload' => [
                        'kode_outlet' => $request->kode_outlet,
                        'latlong_in' => $request->latlong_in,
                        'tipe_visit' => $request->tipe_visit,
                    ],
                    'files' => ['picture_visit'],
                ]);

                $visit = Visit::create([
                    'tanggal_visit' => date('Y-m-d'),
                    'user_id' => $user->id,
                    'outlet_id' => $outletId,
                    'tipe_visit' => $request->tipe_visit,
                    'latlong_in' => $request->latlong_in,
                    'check_in_time' => Carbon::now(),
                    'picture_visit_in' => $temporaryPath,
                ]);

                // Process media files using unified trait
                if ($mediaQueue !== []) {
                    $mediaDispatched = $this->dispatchMediaJob('visit', $visit->id, $mediaQueue);
                    $visit->refresh();
                }

                // Log successful check-in
                Log::channel('visit')->info('Visit check-in success', [
                    'visit_id' => $visit->id,
                    'user_id' => $user->id,
                    'outlet_id' => $outletId,
                    'kode_outlet' => $outlet->kode_outlet,
                ]);

                return ResponseFormatter::success([
                    'visit' => $visit,
                ], 'berhasil check in');
            }
            if ($checkOut) {
                $mediaQueue = [];
                $user = Auth::user();
                $lastDataVisit = Visit::whereDate('tanggal_visit', date('Y-m-d'))->where('user_id', $user->id)->latest()->first();

                // Log check-out request
                Log::channel('visit')->info('VisitController@submit request', [
                    'user_id' => $user->id,
                    'role_id' => $user->role_id,
                    'payload' => [
                        'latlong_out' => $request->latlong_out,
                        'laporan_visit' => $request->laporan_visit,
                        'transaksi' => $request->transaksi,
                    ],
                    'files' => ['picture_visit'],
                ]);

                if ($lastDataVisit != null) {
                    // Validate user hasn't already checked out
                    if ($lastDataVisit->check_out_time !== null) {
                        Log::channel('visit')->warning('Visit check-out failed: already checked out', [
                            'user_id' => $user->id,
                            'visit_id' => $lastDataVisit->id,
                        ]);

                        return ResponseFormatter::error([
                            'message' => 'Anda sudah melakukan check-out',
                            'visit_id' => $lastDataVisit->id,
                            'checked_out_at' => $lastDataVisit->check_out_time,
                        ], 'Visit sudah di check-out sebelumnya', 400);
                    }

                    $request->validate([
                        'latlong_out' => ['required'],
                        'laporan_visit' => ['required'],
                        'picture_visit' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
                        'transaksi' => ['required'],
                    ]);
                    if (! $request->hasFile('picture_visit') || ! $request->file('picture_visit')->isValid()) {
                        return ResponseFormatter::error('File gambar tidak valid', 'INVALID_FILE', 422);
                    }
                    $awal = Carbon::parse($lastDataVisit->check_in_time);
                    $akhir = Carbon::now();
                    $durasi = $awal->diffInMinutes($akhir);
                    $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
                    $imageName = date('Y-m-d').'-'.Auth::user()->username.'-'.'OUT-'.Carbon::now()->getPreciseTimestamp(3).'.'.$ext;
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
                            'type' => 'visit-out', // Use type instead of directory - TRUE FLAT STORAGE!
                            'filename' => $imageName,
                        ];
                    } catch (RuntimeException $e) {
                        $this->cleanupTemporaryFiles($temporaryFiles);

                        return ResponseFormatter::error($e->getMessage(), 'INVALID_FILE', 422);
                    }
                    $data = [
                        'tanggal_visit' => date('Y-m-d'),
                        'latlong_out' => $request->latlong_out,
                        'check_out_time' => now(),
                        'laporan_visit' => $request->laporan_visit,
                        'durasi_visit' => $durasi,
                        'picture_visit_out' => $temporaryPath,
                        'transaksi' => $request->transaksi,
                    ];
                    $lastDataVisit->forceFill($data)->save();

                    // Process media files using unified trait
                    if ($mediaQueue !== []) {
                        $mediaDispatched = $this->dispatchMediaJob('visit', $lastDataVisit->id, $mediaQueue);
                        $lastDataVisit->refresh();
                    }

                    $data['picture_visit_out'] = $lastDataVisit->picture_visit_out;

                    // Log successful check-out with duration
                    Log::channel('visit')->info('Visit check-out success', [
                        'visit_id' => $lastDataVisit->id,
                        'user_id' => $user->id,
                        'durasi' => $durasi.' minutes',
                    ]);

                    return ResponseFormatter::success([
                        'visit' => $data,
                    ], 'berhasil check out');
                } else {
                    return ResponseFormatter::error('Belum ada check-in untuk di check-out', 'INVALID_STATE', 422);
                }
            }
        } catch (Exception $error) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            return ResponseFormatter::error([
                'error' => $error,
            ], 'error', 500);
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
            if (! $path) {
                continue;
            }

            $disk->delete($path);
        }
    }

    // submitNoo removed

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
