<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\DeletePlanVisitRequest;
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

class PlanVisitController extends Controller
{
    public function __construct(protected SystemSettingResolver $systemSettings) {}

    public function fetch(Request $request): JsonResponse
    {
        $request->validate([
            'compact' => ['sometimes', 'boolean'],
            'period' => ['sometimes', 'string', 'in:today,week,month'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'bulan' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'tahun' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
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

        // Priority 1: Legacy bulan/tahun filter (backward compatibility)
        if ($request->has(['bulan', 'tahun'])) {
            $request->validate([
                'bulan' => ['required', 'integer', 'min:1', 'max:12'],
                'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            ]);

            $rangeStart = Carbon::createFromDate((int) $request->tahun, (int) $request->bulan, 1)->startOfMonth();
            $rangeEnd = $rangeStart->copy()->endOfMonth();

            return $this->respondWithPlanCollection(
                $query
                ->where('schedule_scope', 'daily')
                ->whereBetween('period_start', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
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
                            $sub->where('schedule_scope', 'daily')
                                ->whereBetween('period_start', [$dateFrom->toDateString(), $dateTo->toDateString()]);
                        })
                        ->orWhere(function (Builder $sub) use ($dateFrom, $dateTo): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->whereDate('period_start', '<=', $dateTo->toDateString())
                                ->whereDate('period_end', '>=', $dateFrom->toDateString());
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
                // Current week (Monday to Sunday)
                $rangeStart = Carbon::now()->startOfWeek();
                $rangeEnd = Carbon::now()->endOfWeek();

                $query->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
                    $builder
                        ->where(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $sub->where('schedule_scope', 'daily')
                                ->whereBetween('period_start', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
                        })
                        ->orWhere(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->whereDate('period_start', '<=', $rangeEnd->toDateString())
                                ->whereDate('period_end', '>=', $rangeStart->toDateString());
                        });
                });
                break;

            case 'month':
                // Current month
                $rangeStart = Carbon::now()->startOfMonth();
                $rangeEnd = Carbon::now()->endOfMonth();

                $query->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
                    $builder
                        ->where(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $sub->where('schedule_scope', 'daily')
                                ->whereBetween('period_start', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
                        })
                        ->orWhere(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->whereDate('period_start', '<=', $rangeEnd->toDateString())
                                ->whereDate('period_end', '>=', $rangeStart->toDateString());
                        });
                });
                break;

            case 'today':
            default:
                // Today's plan visits (default)
                $today = now()->toDateString();

                $query->where(function (Builder $builder) use ($today): void {
                    $builder
                        ->where(function (Builder $sub) use ($today): void {
                            $sub->where('schedule_scope', 'daily')
                                ->whereDate('period_start', $today);
                        })
                        ->orWhere(function (Builder $sub) use ($today): void {
                            $sub->where('schedule_scope', 'weekly')
                                ->whereDate('period_start', '<=', $today)
                                ->whereDate('period_end', '>=', $today);
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

    private function respondWithPlanCollection(
        Builder $query,
        Request $request,
        bool $compact,
        string $message
    ): JsonResponse {
        $resourceClass = $compact ? PlanVisitCompactResource::class : PlanVisitResource::class;
        $perPage = min((int) $request->input('per_page', 0), 100);

        if ($perPage > 0) {
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
                    ],
                ],
                'errors' => null,
            ])->response();
        }

        $plan = $query->get();

        return $resourceClass::collection($plan)->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => $message,
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

        $existingPlan = PlanVisit::query()
            ->where('user_id', $user->id)
            ->where('visitable_type', $visitableType)
            ->where('visitable_id', $target->id)
            ->where('schedule_scope', 'daily')
            ->whereDate('period_start', $schedulePayload['period_start'])
            ->first();

        if ($existingPlan) {
            Log::channel('planvisit')->warning('Plan visit add failed: duplicate', [
                'user_id' => $user->id,
                'visitable_type' => $visitableType,
                'visitable_id' => $target->id,
                'period_start' => $schedulePayload['period_start'],
            ]);

            throw new BadRequestException('Plan visit untuk target ini sudah ada');
        }

        $addPlan = PlanVisit::create(array_merge($schedulePayload, [
            'user_id' => (string) $user->id,
            'visitable_type' => $visitableType,
            'visitable_id' => $target->id,
        ]));

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

    public function delete(DeletePlanVisitRequest $request): JsonResponse
    {
        $user = Auth::user();

        Log::channel('planvisit')->info('Penghapusan plan visit dimulai', [
            'user_id' => $user->id,
            'payload' => [
                'bulan' => $request->bulan,
                'tahun' => $request->tahun,
                'outlet_id' => $request->outlet_id,
                'register_id' => $request->register_id,
            ],
        ]);

        // Resolve visitable target
        if ($request->filled('outlet_id')) {
            $target = Outlet::visibleTo($user)->find($request->outlet_id);

            if (! $target) {
                Log::channel('planvisit')->warning('Penghapusan plan visit gagal: outlet tidak ditemukan', [
                    'user_id' => $user->id,
                    'outlet_id' => $request->outlet_id,
                ]);

                throw new ResourceNotFoundException('Outlet tidak ditemukan');
            }
            $visitableType = Outlet::class;
        } else {
            $target = Register::visibleTo($user)->find($request->register_id);

            if (! $target) {
                Log::channel('planvisit')->warning('Penghapusan plan visit gagal: register tidak ditemukan', [
                    'user_id' => $user->id,
                    'register_id' => $request->register_id,
                ]);

                throw new ResourceNotFoundException('Register tidak ditemukan');
            }
            $visitableType = Register::class;
        }

        $rangeStart = Carbon::createFromDate((int) $request->tahun, (int) $request->bulan, 1)->startOfMonth();
        $rangeEnd = $rangeStart->copy()->endOfMonth();

        $planVisit = PlanVisit::where('visitable_type', $visitableType)
            ->where('visitable_id', $target->id)
            ->where('user_id', $user->id)
            ->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
                $builder
                    ->where(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                        $sub->where('schedule_scope', 'daily')
                            ->whereBetween('period_start', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
                    })
                    ->orWhere(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                        $sub->where('schedule_scope', 'weekly')
                            ->whereDate('period_start', '<=', $rangeEnd->toDateString())
                            ->whereDate('period_end', '>=', $rangeStart->toDateString());
                    });
            })
            ->first();

        if (! $planVisit) {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: plan tidak ditemukan', [
                'user_id' => $user->id,
                'visitable_type' => $visitableType,
                'visitable_id' => $target->id,
                'bulan' => $request->bulan,
                'tahun' => $request->tahun,
            ]);

            throw new ResourceNotFoundException('Plan visit tidak ditemukan');
        }

        if ($planVisit->schedule_scope === 'weekly') {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: jadwal mingguan tidak dapat dihapus', [
                'user_id' => $user->id,
                'visitable_type' => $visitableType,
                'visitable_id' => $target->id,
                'plan_visit_id' => $planVisit->id,
                'schedule_scope' => $planVisit->schedule_scope,
            ]);

            throw new BadRequestException('Plan visit mingguan tidak dapat dihapus');
        }

        $delete = PlanVisit::where('visitable_type', $visitableType)
            ->where('visitable_id', $target->id)
            ->where('user_id', $user->id)
            ->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
                $builder
                    ->where(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                        $sub->where('schedule_scope', 'daily')
                            ->whereBetween('period_start', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);
                    })
                    ->orWhere(function (Builder $sub) use ($rangeStart, $rangeEnd): void {
                        $sub->where('schedule_scope', 'weekly')
                            ->whereDate('period_start', '<=', $rangeEnd->toDateString())
                            ->whereDate('period_end', '>=', $rangeStart->toDateString());
                    });
            })
            ->delete();

        if (! $delete) {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: tidak ada data yang dihapus', [
                'user_id' => $user->id,
                'visitable_type' => $visitableType,
                'visitable_id' => $target->id,
                'bulan' => $request->bulan,
                'tahun' => $request->tahun,
            ]);

            throw new BadRequestException('Gagal menghapus plan visit');
        }

        Log::channel('planvisit')->info('Penghapusan plan visit berhasil', [
            'user_id' => $user->id,
            'visitable_type' => $visitableType,
            'visitable_id' => $target->id,
            'deleted_count' => $delete,
            'bulan' => $request->bulan,
            'tahun' => $request->tahun,
        ]);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'berhasil',
            ],
            'data' => $delete,
            'errors' => null,
        ]);
    }
}
