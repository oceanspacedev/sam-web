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
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\Visit;
use App\Services\FileUploadService;
use App\Services\SystemSettingResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VisitController extends Controller
{
    use HasMediaUpload;

    private const MAX_LEAD_VISITS_PER_WINDOW = 4;

    public function __construct(
        protected FileUploadService $fileUpload,
        protected SystemSettingResolver $systemSettings,
    ) {}

    public function monitor(Request $request)
    {
        $request->validate([
            'compact' => 'sometimes|boolean',
            'date' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

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
        $visit = Visit::with($baseRelations);
        $this->whereDayRange($visit, 'tanggal_visit', Carbon::parse($date));

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
            $visit->latest();
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

            $visit->latest();
        }

        // Determine resource class based on compact mode
        $resourceClass = $compact ? VisitCompactResource::class : VisitResource::class;
        $perPage = min((int) $request->input('per_page', 50), 100);
        $visits = $visit->simplePaginate($perPage);

        return $resourceClass::collection($visits)->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'fetch monitoring visit success',
                'pagination' => [
                    'current_page' => $visits->currentPage(),
                    'per_page' => $visits->perPage(),
                    'has_more_pages' => $visits->hasMorePages(),
                ],
            ],
            'errors' => null,
        ]);
    }

    public function fetch(Request $request)
    {
        $request->validate([
            'compact' => 'sometimes|boolean',
            'period' => 'sometimes|string|in:today,day,week,month',
            'date' => 'sometimes|date',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'month' => 'sometimes|integer|min:1|max:12',
            'week' => 'sometimes|integer|min:1|max:53',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

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
                    [$rangeStart, $rangeEnd] = $this->resolveWeeklyRange($request);
                    $this->whereDateRange($query, 'tanggal_visit', $rangeStart, $rangeEnd);
                    break;

                case 'month':
                    // Current month, or the month containing the provided anchor date.
                    $monthAnchor = $request->filled('date') ? Carbon::parse($request->date) : now();
                    $query->whereBetween('tanggal_visit', [
                        $monthAnchor->copy()->startOfMonth(),
                        $monthAnchor->copy()->endOfMonth(),
                    ]);
                    break;

                case 'day':
                case 'today':
                default:
                    // Today by default, or the provided historical day.
                    $this->whereDayRange(
                        $query,
                        'tanggal_visit',
                        $request->filled('date') ? Carbon::parse($request->date) : now()
                    );
                    break;
            }
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $query = $query->latest();

        // Determine resource class based on compact mode
        $resourceClass = $compact ? VisitCompactResource::class : VisitResource::class;

        $visit = $query->paginate($perPage);

        return $resourceClass::collection($visit)->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'fetch visit succes',
                'pagination' => [
                    'current_page' => $visit->currentPage(),
                    'per_page' => $visit->perPage(),
                    'total' => $visit->total(),
                    'last_page' => $visit->lastPage(),
                    'has_more_pages' => $visit->hasMorePages(),
                ],
            ],
            'errors' => null,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = Auth::user();

        if (! $user) {
            throw new UnauthorizedException;
        }

        $visitQuery = Visit::with([
            'visitable.badanusaha',
            'visitable.region',
            'visitable.divisi',
            'visitable.cluster',
            'user.badanUsahas',
            'user.regions',
            'user.divisis',
            'user.clusters',
            'user.role',
        ])
            ->whereKey($id);

        if (! $this->canMonitorVisitDetails($user)) {
            $visitQuery->where('user_id', $user->id);
        }

        $visit = $visitQuery->first();

        if (! $visit) {
            throw new ResourceNotFoundException('Visit tidak ditemukan');
        }

        return (new VisitResource($visit))->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'fetch visit detail success',
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
                ->whereNull('check_out_time')
                ->tap(fn (Builder $query) => $this->whereDayRange($query, 'tanggal_visit', today()))
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

                if (! $this->systemSettings->allowsRegisterVisitForModel($target)) {
                    throw new BadRequestException('Setting sistem tidak mengizinkan visit ke LEAD/NOO untuk target ini');
                }

                $registerType = strtoupper((string) $target->type);
                $registerStatus = strtoupper((string) $target->status);

                // Approved register must be visited via Outlet target.
                if ($registerStatus === 'APPROVED') {
                    throw new BadRequestException('Register sudah menjadi outlet, gunakan target outlet');
                }

                if ($registerStatus === 'REJECTED') {
                    throw new BadRequestException('Register sudah REJECTED dan tidak dapat dijadikan target visit');
                }

                if ($registerType === 'LEAD') {
                    $this->enforceLeadVisitLimit($user, $target);
                }
            }

            if ($request->tipe_visit === 'PLANNED') {
                $today = now()->startOfDay();

                $hasPlannedTarget = PlanVisit::query()
                    ->where('user_id', $user->id)
                    ->where('visitable_type', $visitableType)
                    ->where('visitable_id', $target->id)
                    ->unrealized()
                    ->where(function (Builder $query) use ($today): void {
                        $query
                            ->where(function (Builder $daily) use ($today): void {
                                $daily
                                    ->where('schedule_scope', 'daily');
                                $this->whereDayRange($daily, 'period_start', $today);
                            })
                            ->orWhere(function (Builder $weekly) use ($today): void {
                                $weekly
                                    ->where('schedule_scope', 'weekly')
                                    ->where('period_start', '<=', $today->toDateString())
                                    ->where('period_end', '>=', $today->toDateString());
                            });
                    })
                    ->exists();

                if (! $hasPlannedTarget) {
                    throw new BadRequestException('Target ini tidak ada di Plan Visit hari ini. Gunakan EXTRACALL.');
                }
            }

            // Check max visit per day (for both outlet and register)
            $this->enforceMaxVisitPerDay($user, $target);

            // Check duplicate visit
            $existingVisit = Visit::where('user_id', $user->id)
                ->where('visitable_type', $visitableType)
                ->where('visitable_id', $target->id)
                ->tap(fn (Builder $query) => $this->whereDayRange($query, 'tanggal_visit', today()))
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
        // Max visit per day check removed by business rule.
    }

    public function getTargets(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|string',
            'context' => 'sometimes|string|in:extracall,planned',
            'lat' => 'sometimes|numeric|between:-90,90|required_with:lng',
            'lng' => 'sometimes|numeric|between:-180,180|required_with:lat',
            'nearby_radius_km' => 'sometimes|numeric|between:5,10',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $search = trim((string) $request->get('search', ''));
        $context = (string) $request->input('context', 'extracall');
        $hasLimit = $request->filled('limit');
        $limit = min((int) $request->input('limit', 10), 100);
        $nearbyRadiusKm = (float) $request->input('nearby_radius_km', 10);
        $hasCoordinates = $request->filled(['lat', 'lng']);
        $useNearestTargets = $context === 'extracall' && $search === '' && $hasCoordinates;

        $outletsQuery = Outlet::with(['badanusaha:id,name', 'divisi:id,name', 'region:id,name', 'cluster:id,name'])
            ->visibleTo($user)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('nama_outlet', 'like', "%{$search}%")
                        ->orWhere('kode_outlet', 'like', "%{$search}%");
                });
            });

        $registersQuery = Register::visibleTo($user)
            // Avoid duplicate target when register has already become an outlet.
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['APPROVED', 'REJECTED']);
            })
            ->whereDoesntHave('outlet')
            ->with(['badanusaha:id,name', 'divisi:id,name', 'region:id,name', 'cluster:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('nama_outlet', 'like', "%{$search}%")
                        ->orWhere('kode_outlet', 'like', "%{$search}%");
                });
            });

        if ($useNearestTargets) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            $candidateLimit = $hasLimit ? min(max($limit * 3, 30), 300) : null;

            $outlets = $this->applyNearbyLocation($outletsQuery, $lat, $lng, $nearbyRadiusKm, $candidateLimit)
                ->get()
                ->map(function (Outlet $outlet) use ($lat, $lng): ?array {
                    $distance = $this->distanceFromLatlong($outlet->latlong, $lat, $lng);
                    if ($distance === null) {
                        return null;
                    }

                    $target = $this->formatOutletTarget($outlet);
                    $target['_distance'] = $distance;

                    return $target;
                })
                ->filter(fn (?array $target): bool => $target !== null)
                ->values();

            $registers = $this->applyNearbyLocation($registersQuery, $lat, $lng, $nearbyRadiusKm)
                ->get()
                ->filter(fn (Register $register): bool => $this->systemSettings->allowsRegisterVisitForModel($register))
                ->values()
                ->map(function (Register $register) use ($lat, $lng): ?array {
                    $distance = $this->distanceFromLatlong($register->latlong, $lat, $lng);
                    if ($distance === null) {
                        return null;
                    }

                    $target = $this->formatRegisterTarget($register);
                    $target['_distance'] = $distance;

                    return $target;
                })
                ->filter(fn (?array $target): bool => $target !== null)
                ->values();

            $targets = $outlets->concat($registers)
                ->filter(fn (array $target): bool => ($target['_distance'] ?? INF) <= $nearbyRadiusKm)
                ->sort(function (array $first, array $second): int {
                    $distanceCompare = ($first['_distance'] ?? INF) <=> ($second['_distance'] ?? INF);
                    if ($distanceCompare !== 0) {
                        return $distanceCompare;
                    }

                    return strcasecmp((string) ($first['nama'] ?? ''), (string) ($second['nama'] ?? ''));
                })
                ->map(function (array $target): array {
                    unset($target['_distance']);

                    return $target;
                })
                ->values();

            if ($hasLimit) {
                $targets = $targets->take($limit)->values();
            }
        } else {
            $outlets = $outletsQuery
                ->orderBy('nama_outlet')
                ->limit($limit)
                ->get()
                ->map(fn (Outlet $outlet): array => $this->formatOutletTarget($outlet));

            $registers = $registersQuery
                ->orderBy('nama_outlet')
                ->limit($limit)
                ->get()
                ->filter(fn (Register $register): bool => $this->systemSettings->allowsRegisterVisitForModel($register))
                ->values()
                ->map(fn (Register $register): array => $this->formatRegisterTarget($register));

            // Combine results
            $targets = $outlets->concat($registers)
                ->sortBy(fn ($target): string => strtolower((string) ($target['nama'] ?? '')))
                ->take($limit)
                ->values();
        }

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

    private function formatOutletTarget(Outlet $outlet): array
    {
        return [
            'id' => $outlet->id,
            'type' => 'outlet',
            'target_type' => 'outlet',
            'target_type_label' => 'OUTLET',
            'kode' => $outlet->kode_outlet,
            'nama' => $outlet->nama_outlet,
            'latlong' => $outlet->latlong,
            'alamat' => $outlet->alamat_outlet,
            'distric' => $outlet->distric,
            'badanusaha' => $outlet->badanusaha->name ?? '-',
            'divisi' => $outlet->divisi->name ?? '-',
            'region' => $outlet->region->name ?? '-',
            'cluster' => $outlet->cluster->name ?? '-',
            'plan_visit_min_days' => $this->systemSettings->planVisitMinDaysForIds(
                $outlet->badanusaha_id ? (int) $outlet->badanusaha_id : null,
                $outlet->divisi_id ? (int) $outlet->divisi_id : null,
                $outlet->region_id ? (int) $outlet->region_id : null,
                $outlet->cluster_id ? (int) $outlet->cluster_id : null,
            ),
            'radius' => $outlet->radius ? (int) $outlet->radius : 100,
        ];
    }

    private function formatRegisterTarget(Register $register): array
    {
        $registerType = strtoupper((string) $register->type) === 'LEAD' ? 'LEAD' : 'NOO';

        return [
            'id' => $register->id,
            'type' => 'register',
            'target_type' => 'register',
            'target_type_label' => 'REGISTER',
            'kode' => $register->kode_outlet,
            'nama' => $register->nama_outlet,
            'latlong' => $register->latlong,
            'alamat' => $register->alamat_outlet,
            'distric' => $register->distric,
            'badanusaha' => $register->badanusaha->name ?? '-',
            'divisi' => $register->divisi->name ?? '-',
            'region' => $register->region->name ?? '-',
            'cluster' => $register->cluster->name ?? '-',
            'type_register' => $registerType,
            'register_type' => $registerType,
            'plan_visit_min_days' => $this->systemSettings->planVisitMinDaysForIds(
                $register->badanusaha_id ? (int) $register->badanusaha_id : null,
                $register->divisi_id ? (int) $register->divisi_id : null,
                $register->region_id ? (int) $register->region_id : null,
                $register->cluster_id ? (int) $register->cluster_id : null,
            ),
            'radius' => $this->systemSettings->defaultRegisterRadiusForIds(
                $register->badanusaha_id ? (int) $register->badanusaha_id : null,
                $register->divisi_id ? (int) $register->divisi_id : null,
                $register->region_id ? (int) $register->region_id : null,
                $register->cluster_id ? (int) $register->cluster_id : null,
            ),
        ];
    }

    private function distanceFromLatlong(?string $latlong, float $userLat, float $userLng): ?float
    {
        if (! $latlong) {
            return null;
        }

        $parts = explode(',', $latlong);
        if (count($parts) < 2) {
            return null;
        }

        $targetLatRaw = trim($parts[0]);
        $targetLngRaw = trim($parts[1]);
        if (! is_numeric($targetLatRaw) || ! is_numeric($targetLngRaw)) {
            return null;
        }

        $targetLat = (float) $targetLatRaw;
        $targetLng = (float) $targetLngRaw;

        if ($targetLat < -90 || $targetLat > 90 || $targetLng < -180 || $targetLng > 180) {
            return null;
        }

        $latDistance = deg2rad($targetLat - $userLat);
        $lngDistance = deg2rad($targetLng - $userLng);

        $a = sin($latDistance / 2) ** 2
            + cos(deg2rad($userLat))
            * cos(deg2rad($targetLat))
            * sin($lngDistance / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));

        return 6371 * $c;
    }

    private function whereDayRange(Builder $query, string $column, Carbon $date): Builder
    {
        $start = $date->copy()->startOfDay()->toDateString();
        $end = $date->copy()->addDay()->startOfDay()->toDateString();

        return $query->where($column, '>=', $start)->where($column, '<', $end);
    }

    private function whereDateRange(Builder $query, string $column, Carbon $start, Carbon $end): Builder
    {
        $startDate = $start->copy()->startOfDay()->toDateString();
        $endDate = $end->copy()->addDay()->startOfDay()->toDateString();

        return $query->where($column, '>=', $startDate)->where($column, '<', $endDate);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveWeeklyRange(Request $request): array
    {
        if ($request->filled(['year', 'week'])) {
            $start = Carbon::now()
                ->setISODate($request->integer('year'), $request->integer('week'))
                ->startOfDay();

            return [$start, $start->copy()->addDays(6)];
        }

        $anchor = $request->filled('date') ? Carbon::parse($request->date) : now();
        $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        return [$start, $start->copy()->addDays(6)];
    }

    private function canMonitorVisitDetails($user): bool
    {
        return $user
            && $user->can('ViewAny:Visit');
    }

    private function applyNearbyLocation(
        Builder $query,
        float $latitude,
        float $longitude,
        float $radiusKm,
        ?int $limit = null
    ): Builder {
        $query
            ->whereNotNull('latlong')
            ->where('latlong', '!=', '')
            ->where('latlong', '!=', '-');

        if (! $this->supportsSqlLatlongDistance($query)) {
            return $query;
        }

        $distanceFormula = "
            (
                6371 * acos(
                    cos(radians(?))
                    * cos(radians(CAST(SUBSTRING_INDEX(latlong, ',', 1) AS DECIMAL(10, 8))))
                    * cos(radians(CAST(SUBSTRING_INDEX(latlong, ',', -1) AS DECIMAL(11, 8))) - radians(?))
                    + sin(radians(?))
                    * sin(radians(CAST(SUBSTRING_INDEX(latlong, ',', 1) AS DECIMAL(10, 8))))
                )
            )
        ";
        $distanceBindings = [$latitude, $longitude, $latitude];
        $existingColumns = $query->getQuery()->columns;

        if (empty($existingColumns)) {
            $query->selectRaw("*, {$distanceFormula} AS distance", $distanceBindings);
        } else {
            $query->selectRaw("{$distanceFormula} AS distance", $distanceBindings);
        }

        $query
            ->whereRaw("{$distanceFormula} <= ?", [...$distanceBindings, $radiusKm])
            ->orderBy('distance');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query;
    }

    private function supportsSqlLatlongDistance(Builder $query): bool
    {
        return in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function enforceLeadVisitLimit($user, Register $target): void
    {
        $windowStart = now()->startOfMonth();

        $leadVisitCount = Visit::query()
            ->where('user_id', $user->id)
            ->where('visitable_type', Register::class)
            ->where('visitable_id', $target->id)
            ->where('tanggal_visit', '>=', $windowStart->toDateString())
            ->count();

        if ($leadVisitCount < self::MAX_LEAD_VISITS_PER_WINDOW) {
            return;
        }

        throw (new BadRequestException(
            'Lead ini sudah di-visit '.self::MAX_LEAD_VISITS_PER_WINDOW.'x dalam bulan ini. Upgrade ke NOO atau update status lead terlebih dahulu.'
        ))->withData([
            'lead_visit_count_in_window' => $leadVisitCount,
            'max_lead_visits_in_window' => self::MAX_LEAD_VISITS_PER_WINDOW,
            'lead_visit_window_start' => $windowStart->toDateString(),
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
