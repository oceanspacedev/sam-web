<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Exceptions\Api\UnauthorizedException;
use App\Http\Controllers\API\Traits\HasMediaUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\CheckinVisitRequest;
use App\Http\Requests\API\CheckoutVisitRequest;
use App\Http\Resources\Visit\VisitCompactResource;
use App\Http\Resources\Visit\VisitResource;
use App\Models\DivisionSetting;
use App\Models\Outlet;
use App\Models\Register;
use App\Models\Visit;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VisitController extends Controller
{
    use HasMediaUpload;

    public function __construct(protected FileUploadService $fileUpload) {}

    public function monitor(Request $request)
    {
        $user = Auth::user();
        $compact = $request->boolean('compact', true);

        // Validate access
        if (! $user || ! $user->role) {
            throw new UnauthorizedException;
        }

        // SDUI: Check permission
        if (! $user->can('ViewAny:Visit')) {
            throw new UnauthorizedException('Anda tidak memiliki akses untuk monitoring visit');
        }

        $scopeLevel = $user->role->organizational_scope_level;

        if (! $scopeLevel) {
            throw new UnauthorizedException;
        }

        $date = $request->date ? Carbon::parse($request->date)->toDateString() : now()->toDateString();

        $baseRelations = $compact
            ? [
                'visitable:id,kode_outlet,nama_outlet',
                'user:id,nama_lengkap,role_id',
            ]
            : [
                'visitable.badanusaha',
                'visitable.region',
                'visitable.divisi',
                'visitable.cluster',
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
                throw new UnauthorizedException('User tidak memiliki organizational assignment');
            }
        }

        // Build query with scope-based filtering
        $visit = Visit::with($baseRelations)->whereDate('tanggal_visit', $date);

        if ($compact) {
            $visit->select([
                'id',
                'user_id',
                'visitable_type',
                'visitable_id',
                'tanggal_visit',
                'tipe_visit',
                'check_in_time',
                'check_out_time',
                'transaksi',
                'created_at',
                'picture_visit_in',
                'picture_visit_out',
                'latlong_in',
                'latlong_out',
                'laporan_visit',
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

        // Determine resource class based on compact mode
        $resourceClass = $compact ? VisitCompactResource::class : VisitResource::class;

        return $resourceClass::collection($visit)->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'fetch monitoring visit success',
            ],
            'errors' => null,
        ]);
    }

    public function fetch(Request $request)
    {
        $compact = $request->boolean('compact', true);

        $query = Visit::with($compact ? [
            'visitable:id,kode_outlet,nama_outlet',
            'user:id,nama_lengkap,role_id',
        ] : [
            'visitable.badanusaha',
            'visitable.region',
            'visitable.divisi',
            'visitable.cluster',
            'user.badanUsahas',
            'user.regions',
            'user.divisis',
            'user.clusters',
            'user.role',
        ])->where('user_id', Auth::id());

        if ($compact) {
            $query->select([
                'id',
                'user_id',
                'visitable_type',
                'visitable_id',
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
        // Priority: custom range (date_from & date_to) > year+month > period
        if ($request->filled(['date_from', 'date_to'])) {
            // Custom date range
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->whereBetween('tanggal_visit', [$dateFrom, $dateTo]);
        } elseif ($request->filled(['year', 'month'])) {
            // Specific year and month
            $year = (int) $request->year;
            $month = (int) $request->month;
            $query->whereBetween('tanggal_visit', [
                Carbon::create($year, $month, 1)->startOfMonth(),
                Carbon::create($year, $month, 1)->endOfMonth(),
            ]);
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

        // Determine resource class based on compact mode
        $resourceClass = $compact ? VisitCompactResource::class : VisitResource::class;

        return $resourceClass::collection($visit)->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'fetch visit succes',
            ],
            'errors' => null,
        ]);
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
                $name = $activeVisit->visitable->nama_outlet ?? $activeVisit->visitable->kode_outlet ?? 'target';
                throw (new BadRequestException(
                    "Belum check out dari {$name}"
                ))->withData(['active_visit_id' => $activeVisit->id]);
            }

            // Resolve visitable target
            if ($request->filled('outlet_id')) {
                $target = Outlet::visibleTo($user)->where('id', $request->outlet_id)->first();
                if (! $target) {
                    Log::channel('visit')->warning('Check-in visit gagal: outlet tidak ditemukan', [
                        'user_id' => $user->id,
                        'outlet_id' => $request->outlet_id,
                    ]);

                    throw new ResourceNotFoundException('Outlet tidak ditemukan');
                }
                $visitableType = Outlet::class;
            } else {
                $target = Register::visibleTo($user)->where('id', $request->register_id)->first();
                if (! $target) {
                    Log::channel('visit')->warning('Check-in visit gagal: register tidak ditemukan', [
                        'user_id' => $user->id,
                        'register_id' => $request->register_id,
                    ]);

                    throw new ResourceNotFoundException('Register tidak ditemukan');
                }
                $visitableType = Register::class;

                // Check division settings
                $divisionSetting = DivisionSetting::where('division_id', $target->divisi_id)->first();
                if (! $divisionSetting || ! $divisionSetting->allow_register_visit) {
                    throw new BadRequestException('Divisi ini tidak mengizinkan visit ke LEAD/NOO');
                }
            }

            // Check max visit per day (for both outlet and register)
            $this->enforceMaxVisitPerDay($user, $target);

            // Check duplicate visit
            $existingVisit = Visit::where('user_id', $user->id)
                ->where('visitable_type', $visitableType)
                ->where('visitable_id', $target->id)
                ->whereDate('tanggal_visit', today())
                ->first();

            if ($existingVisit) {
                $name = $target->nama_outlet ?? $target->kode_outlet ?? 'target';
                throw (new BadRequestException(
                    "Anda sudah pernah visit ke {$name} hari ini"
                ))->withData(['existing_visit_id' => $existingVisit->id]);
            }

            $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
            $imageName = date('Y-m-d').'-'.$user->username.'-IN-'.Carbon::now()->getPreciseTimestamp(3).'.'.$ext;

            // FileUploadService throws RuntimeException on error, Handler will catch it
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

            // Create visit with polymorphic fields
            $visit = Visit::create([
                'tanggal_visit' => today(),
                'user_id' => $user->id,
                'visitable_type' => $visitableType,
                'visitable_id' => $target->id,
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

            $name = $target->nama_outlet ?? $target->kode_outlet ?? 'target';
            Log::channel('visit')->info('Check-in visit berhasil', [
                'visit_id' => $visit->id,
                'user_id' => $user->id,
                'visitable_type' => $visitableType,
                'visitable_id' => $target->id,
                'name' => $name,
            ]);

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Check-in berhasil',
                ],
                'data' => new VisitResource($visit),
                'errors' => null,
            ]);
        } finally {
            // Cleanup temporary files if media job wasn't dispatched
            if (! $mediaDispatched && $temporaryFiles !== []) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }
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
                throw new ResourceNotFoundException('Visit tidak ditemukan atau sudah check-out');
            }

            $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
            $imageName = date('Y-m-d').'-'.$user->username.'-OUT-'.Carbon::now()->getPreciseTimestamp(3).'.'.$ext;

            // FileUploadService throws RuntimeException on error, Handler will catch it
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

            Log::channel('visit')->info('Check-out visit berhasil', [
                'visit_id' => $visit->id,
                'user_id' => $user->id,
                'durasi' => $duration.' minutes',
            ]);

            return response()->json([
                'meta' => ['code' => 200, 'status' => 'success', 'message' => 'Check-out berhasil'],
                'data' => new VisitResource($visit),
                'errors' => null,
            ]);
        } finally {
            // Cleanup temporary files if media job wasn't dispatched
            if (! $mediaDispatched && $temporaryFiles !== []) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }
        }
    }

    private function enforceMaxVisitPerDay($user, Outlet|Register $target): void
    {
        // Max visit per day check removed - no longer using DivisionSettings
    }

    public function getTargets(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|string',
        ]);

        $user = Auth::user();
        $search = $request->get('search');

        // Get outlets
        $outlets = Outlet::with(['badanusaha:id,name', 'divisi:id,name', 'region:id,name', 'cluster:id,name'])
            ->visibleTo($user)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_outlet', 'like', "%{$search}%")
                        ->orWhere('kode_outlet', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_outlet')
            ->get()
            ->map(function ($outlet) {
                return [
                    'id' => $outlet->id,
                    'type' => 'outlet',
                    'kode' => $outlet->kode_outlet,
                    'nama' => $outlet->nama_outlet,
                    'latlong' => $outlet->latlong,
                    'alamat' => $outlet->alamat_outlet,
                    'distric' => $outlet->distric,
                    'badanusaha' => $outlet->badanusaha->name ?? '-',
                    'divisi' => $outlet->divisi->name ?? '-',
                    'region' => $outlet->region->name ?? '-',
                    'cluster' => $outlet->cluster->name ?? '-',
                ];
            });

        // Get registers from divisions that allow visit
        $registers = Register::visibleTo($user)
            ->whereHas('divisi.setting', function ($q) {
                $q->where('allow_register_visit', true);
            })
            ->with(['badanusaha:id,name', 'divisi:id,name', 'region:id,name', 'cluster:id,name'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_outlet', 'like', "%{$search}%")
                        ->orWhere('kode_outlet', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_outlet')
            ->get()
            ->map(function ($register) {
                return [
                    'id' => $register->id,
                    'type' => 'register',
                    'kode' => $register->kode_outlet,
                    'nama' => $register->nama_outlet,
                    'latlong' => $register->latlong,
                    'alamat' => $register->alamat_outlet,
                    'distric' => $register->distric,
                    'badanusaha' => $register->badanusaha->name ?? '-',
                    'divisi' => $register->divisi->name ?? '-',
                    'region' => $register->region->name ?? '-',
                    'cluster' => $register->cluster->name ?? '-',
                    'type_register' => strtoupper($register->type ?? 'lead'),
                ];
            });

        // Combine results
        $targets = $outlets->concat($registers);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'berhasil mendapatkan target visit',
            ],
            'data' => $targets,
            'errors' => null,
        ]);
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
}
