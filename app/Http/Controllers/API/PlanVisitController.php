<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StorePlanVisitRequest;
use App\Http\Resources\PlanVisit\PlanVisitCompactResource;
use App\Http\Resources\PlanVisit\PlanVisitResource;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Services\SystemSettingResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PlanVisitController extends Controller
{
    public function __construct(protected SystemSettingResolver $systemSettings) {}

    public function fetch(Request $request): JsonResponse
    {
        $request->validate([
            'compact' => ['sometimes', 'boolean'],
            'period' => ['sometimes', 'string', 'in:today,day,week,month'],
            'date' => ['sometimes', 'date'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'bulan' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'tahun' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'week' => ['sometimes', 'integer', 'min:1', 'max:53'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $compact = $request->boolean('compact', true);

        $baseRelations = $compact
            ? [
                'visitable' => function (MorphTo $morphTo): void {
                    $morphTo->constrain([
                        Outlet::class => function (Builder $query): void {
                            $query->select([
                                'id',
                                'kode_outlet',
                                'nama_outlet',
                                'distric',
                                'latlong',
                                'alamat_outlet',
                                'radius',
                                'badanusaha_id',
                                'divisi_id',
                                'region_id',
                                'cluster_id',
                            ]);
                        },
                        Register::class => function (Builder $query): void {
                            $query->select([
                                'id',
                                'kode_outlet',
                                'nama_outlet',
                                'distric',
                                'latlong',
                                'alamat_outlet',
                                'type',
                                'badanusaha_id',
                                'divisi_id',
                                'region_id',
                                'cluster_id',
                            ]);
                        },
                    ]);
                },
                'user:id,nama_lengkap',
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

        $query = PlanVisit::with($baseRelations)
            ->where('user_id', Auth::id())
            ->unrealized();

        if ($compact) {
            $query->select([
                'id',
                'user_id',
                'visitable_type',
                'visitable_id',
                'schedule_scope',
                'period_start',
                'period_end',
                'schedule_week',
                'schedule_year',
                'tanggal_visit',
                'realized_at',
                'realized_visit_id',
                'created_at',
                'updated_at',
            ]);
        }

        $month = $request->integer('bulan') ?: $request->integer('month');
        $year = $request->integer('tahun') ?: $request->integer('year');

        // Priority 1: Legacy bulan/tahun filter and month/year aliases.
        if ($month && $year && ! $request->filled('week')) {
            $rangeStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $rangeEnd = $rangeStart->copy()->endOfMonth();

            return $this->respondWithPlanCollection(
                $query
                ->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
                    $builder
                        ->where(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $this->wherePlanPeriodRange(
                                $sub->where('schedule_scope', 'daily'),
                                $rangeStart,
                                $rangeEnd
                            );
                        })
                        ->orWhere(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->where('period_start', '<=', $rangeEnd->toDateString())
                                ->where('period_end', '>=', $rangeStart->toDateString());
                        });
                })
                ->orderBy('period_start'),
                $request,
                $compact,
                'berhasil'
            );
        }

        // Priority 2: Custom date range filter
        if ($request->filled(['date_from', 'date_to'])) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();

            return $this->respondWithPlanCollection(
                $query->where(function (Builder $builder) use ($dateFrom, $dateTo): void {
                    $builder
                        ->where(function (Builder $sub) use ($dateFrom, $dateTo): void {
                            $this->wherePlanPeriodRange(
                                $sub->where('schedule_scope', 'daily'),
                                $dateFrom,
                                $dateTo
                            );
                        })
                        ->orWhere(function (Builder $sub) use ($dateFrom, $dateTo): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->where('period_start', '<=', $dateTo->toDateString())
                                ->where('period_end', '>=', $dateFrom->toDateString());
                        });
                })->orderBy('period_start'),
                $request,
                $compact,
                'berhasil'
            );
        }

        // Priority 3: Period-based filtering
        $period = $request->input('period', 'today');

        switch ($period) {
            case 'week':
                [$rangeStart, $rangeEnd] = $this->resolveWeeklyRange($request);

                $query->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
                    $builder
                        ->where(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $this->wherePlanPeriodRange(
                                $sub->where('schedule_scope', 'daily'),
                                $rangeStart,
                                $rangeEnd
                            );
                        })
                        ->orWhere(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->where('period_start', '<=', $rangeEnd->toDateString())
                                ->where('period_end', '>=', $rangeStart->toDateString());
                        });
                });
                break;

            case 'month':
                // Current month, or the month containing the provided anchor date.
                $monthAnchor = $request->filled('date') ? Carbon::parse($request->date) : now();
                $rangeStart = $monthAnchor->copy()->startOfMonth();
                $rangeEnd = $monthAnchor->copy()->endOfMonth();

                $query->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
                    $builder
                        ->where(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $this->wherePlanPeriodRange(
                                $sub->where('schedule_scope', 'daily'),
                                $rangeStart,
                                $rangeEnd
                            );
                        })
                        ->orWhere(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->where('period_start', '<=', $rangeEnd->toDateString())
                                ->where('period_end', '>=', $rangeStart->toDateString());
                        });
                });
                break;

            case 'day':
            case 'today':
            default:
                // Today's plan visits by default, or the provided historical day.
                $today = $request->filled('date')
                    ? Carbon::parse($request->date)->startOfDay()
                    : now()->startOfDay();

                $query->where(function (Builder $builder) use ($today): void {
                    $builder
                        ->where(function (Builder $sub) use ($today): void {
                            $this->wherePlanPeriodRange(
                                $sub->where('schedule_scope', 'daily'),
                                $today,
                                $today
                            );
                        })
                        ->orWhere(function (Builder $sub) use ($today): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->where('period_start', '<=', $today->toDateString())
                                ->where('period_end', '>=', $today->toDateString());
                        });
                });
                break;
        }

        return $this->respondWithPlanCollection(
            $query->orderBy('period_start'),
            $request,
            $compact,
            'ok'
        );
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

    private function wherePlanPeriodRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query
            ->where('period_start', '>=', $start->copy()->startOfDay()->toDateString())
            ->where('period_start', '<', $end->copy()->addDay()->startOfDay()->toDateString());
    }

    private function respondWithPlanCollection(
        Builder $query,
        Request $request,
        bool $compact,
        string $message
    ): JsonResponse {
        $resourceClass = $compact ? PlanVisitCompactResource::class : PlanVisitResource::class;
        $perPage = min((int) $request->input('per_page', 100), 100);

        $plan = $query->paginate($perPage);

        return $resourceClass::collection($plan)->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => $message,
                'pagination' => [
                    'current_page' => $plan->currentPage(),
                    'per_page' => $plan->perPage(),
                    'total' => $plan->total(),
                    'last_page' => $plan->lastPage(),
                    'has_more_pages' => $plan->hasMorePages(),
                ],
            ],
            'errors' => null,
        ])->response();
    }

    public function store(StorePlanVisitRequest $request): JsonResponse
    {
        $user = Auth::user();

        Log::channel('planvisit')->info('Plan visit add initiated', [
            'user_id' => $user->id,
            'payload' => [
                'tanggal_visit' => $request->tanggal_visit,
                'outlet_id' => $request->outlet_id,
                'register_id' => $request->register_id,
            ],
        ]);

        // Resolve visitable target
        if ($request->filled('outlet_id')) {
            $target = Outlet::visibleTo($user)->find($request->outlet_id);

            if (! $target) {
                Log::channel('planvisit')->warning('Plan visit add failed: outlet not found', [
                    'user_id' => $user->id,
                    'outlet_id' => $request->outlet_id,
                ]);

                throw new ResourceNotFoundException('Outlet tidak ditemukan');
            }
            $visitableType = Outlet::class;
        } else {
            $target = Register::visibleTo($user)->find($request->register_id);

            if (! $target) {
                Log::channel('planvisit')->warning('Plan visit add failed: register not found', [
                    'user_id' => $user->id,
                    'register_id' => $request->register_id,
                ]);

                throw new ResourceNotFoundException('Register tidak ditemukan');
            }

            $registerStatus = strtoupper((string) $target->status);

            if ($registerStatus === 'APPROVED') {
                throw new BadRequestException('Register sudah menjadi outlet, gunakan target outlet');
            }

            if ($registerStatus === 'REJECTED') {
                throw new BadRequestException('Register sudah REJECTED dan tidak dapat dijadikan target plan visit');
            }

            if (! $this->systemSettings->allowsRegisterVisitForModel($target)) {
                throw new BadRequestException('Setting sistem tidak mengizinkan visit/plan visit ke LEAD/NOO untuk target ini');
            }

            $visitableType = Register::class;
        }

        $periodStart = Carbon::parse($request->tanggal_visit)->startOfDay();
        $minPlanDays = $this->systemSettings->planVisitMinDaysForIds(
            $target->badanusaha_id ? (int) $target->badanusaha_id : null,
            $target->divisi_id ? (int) $target->divisi_id : null,
            $target->region_id ? (int) $target->region_id : null,
            $target->cluster_id ? (int) $target->cluster_id : null,
            3
        );
        $minAllowedDate = now()->startOfDay()->addDays($minPlanDays);
        if ($periodStart->lt($minAllowedDate)) {
            throw new BadRequestException(
                $minPlanDays > 0
                    ? "Plan visit harus dibuat minimal H+{$minPlanDays} dari hari ini"
                    : 'Plan visit tidak boleh di tanggal lampau'
            );
        }

        $schedulePayload = PlanVisit::schedulePayload($periodStart, 'daily');

        $attributes = array_merge($schedulePayload, [
            'user_id' => (string) $user->id,
            'visitable_type' => $visitableType,
            'visitable_id' => $target->id,
        ]);

        try {
            $addPlan = PlanVisit::createOrRestoreForPeriod($attributes);
        } catch (ValidationException $exception) {
            Log::channel('planvisit')->warning('Plan visit add failed: duplicate', [
                'user_id' => $user->id,
                'visitable_type' => $visitableType,
                'visitable_id' => $target->id,
                'period_start' => $schedulePayload['period_start'],
            ]);

            throw new BadRequestException('Plan visit untuk target ini sudah ada');
        }

        Log::channel('planvisit')->info('Plan visit add success', [
            'plan_visit_id' => $addPlan->id,
            'user_id' => $user->id,
            'visitable_type' => $visitableType,
            'visitable_id' => $target->id,
            'schedule_scope' => $addPlan->schedule_scope,
            'period_start' => $addPlan->period_start,
            'period_end' => $addPlan->period_end,
        ]);

        return (new PlanVisitResource($addPlan->fresh()))->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'berhasil',
            ],
            'errors' => null,
        ])->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        Log::channel('planvisit')->info('Penghapusan plan visit dimulai', [
            'user_id' => $user->id,
            'plan_visit_id' => $id,
        ]);

        $planVisit = PlanVisit::query()
            ->whereKey($id)
            ->where('user_id', $user->id)
            ->first();

        if (! $planVisit) {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: plan tidak ditemukan', [
                'user_id' => $user->id,
                'plan_visit_id' => $id,
            ]);

            throw new ResourceNotFoundException('Plan visit tidak ditemukan');
        }

        if ($planVisit->schedule_scope === 'weekly') {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: jadwal mingguan tidak dapat dihapus', [
                'user_id' => $user->id,
                'plan_visit_id' => $planVisit->id,
                'schedule_scope' => $planVisit->schedule_scope,
            ]);

            throw new BadRequestException('Plan visit mingguan tidak dapat dihapus');
        }

        $deleted = $planVisit->delete();

        if (! $deleted) {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: tidak ada data yang dihapus', [
                'user_id' => $user->id,
                'plan_visit_id' => $id,
            ]);

            throw new BadRequestException('Gagal menghapus plan visit');
        }

        Log::channel('planvisit')->info('Penghapusan plan visit berhasil', [
            'user_id' => $user->id,
            'plan_visit_id' => $planVisit->id,
            'deleted_count' => 1,
        ]);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'berhasil',
            ],
            'data' => 1,
            'errors' => null,
        ]);
    }
}
