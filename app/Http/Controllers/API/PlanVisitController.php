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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlanVisitController extends Controller
{
    public function fetch(Request $request): JsonResponse
    {
        $compact = $request->boolean('compact', true);

        $baseRelations = $compact
            ? [
                'outlet:id,kode_outlet,nama_outlet,distric,latlong,alamat_outlet',
                'user:id,nama_lengkap',
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

        $query = PlanVisit::with($baseRelations)
            ->where('user_id', Auth::id())
            ->unrealized();

        if ($compact) {
            $query->select([
                'id',
                'user_id',
                'outlet_id',
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
                'bulan' => ['required', 'string'],
                'tahun' => ['required', 'string'],
            ]);

            $rangeStart = Carbon::createFromDate((int) $request->tahun, (int) $request->bulan, 1)->startOfMonth();
            $rangeEnd = $rangeStart->copy()->endOfMonth();

            $plan = $query
                ->where('schedule_scope', 'daily')
                ->whereBetween('period_start', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->orderBy('period_start')
                ->get();

            // Determine resource class based on compact mode
            $resourceClass = $compact ? PlanVisitCompactResource::class : PlanVisitResource::class;

            return $resourceClass::collection($plan)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ])->response();
        }

        // Priority 2: Custom date range filter
        if ($request->filled(['date_from', 'date_to'])) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();

            $plan = $query->where(function (Builder $builder) use ($dateFrom, $dateTo): void {
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
            })
                ->orderBy('period_start')
                ->get();

            // Determine resource class based on compact mode
            $resourceClass = $compact ? PlanVisitCompactResource::class : PlanVisitResource::class;

            return $resourceClass::collection($plan)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ])->response();
        }

        // Priority 3: Period-based filtering
        $period = $request->input('period', 'today');

        switch ($period) {
            case 'week':
                // Current week (Monday to Sunday)
                $rangeStart = Carbon::now()->startOfWeek();
                $rangeEnd = Carbon::now()->endOfWeek();

                $plan = $query->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
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
                    ->orderBy('period_start')
                    ->get();
                break;

            case 'month':
                // Current month
                $rangeStart = Carbon::now()->startOfMonth();
                $rangeEnd = Carbon::now()->endOfMonth();

                $plan = $query->where(function (Builder $builder) use ($rangeStart, $rangeEnd): void {
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
                    ->orderBy('period_start')
                    ->get();
                break;

            case 'today':
            default:
                // Today's plan visits (default)
                $today = now()->toDateString();

                $plan = $query->where(function (Builder $builder) use ($today): void {
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
                })
                    ->orderBy('period_start')
                    ->get();
                break;
        }

        // Determine resource class based on compact mode
        $resourceClass = $compact ? PlanVisitCompactResource::class : PlanVisitResource::class;

        return $resourceClass::collection($plan)->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'ok',
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
            ],
        ]);

        $outlet = Outlet::visibleTo($user)->find($request->outlet_id);

        if (! $outlet) {
            Log::channel('planvisit')->warning('Plan visit add failed: outlet not found', [
                'user_id' => $user->id,
                'outlet_id' => $request->outlet_id,
            ]);

            throw new ResourceNotFoundException('Outlet tidak ditemukan');
        }

        $periodStart = Carbon::parse($request->tanggal_visit)->startOfDay();
        $schedulePayload = PlanVisit::schedulePayload($periodStart, 'daily');

        $existingPlan = PlanVisit::query()
            ->where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->where('schedule_scope', 'daily')
            ->whereDate('period_start', $schedulePayload['period_start'])
            ->first();

        if ($existingPlan) {
            Log::channel('planvisit')->warning('Plan visit add failed: duplicate', [
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'period_start' => $schedulePayload['period_start'],
            ]);

            throw new BadRequestException('Plan visit untuk outlet ini sudah ada');
        }

        $addPlan = PlanVisit::create(array_merge($schedulePayload, [
            'user_id' => (string) $user->id,
            'outlet_id' => $outlet->id,
        ]));

        Log::channel('planvisit')->info('Plan visit add success', [
            'plan_visit_id' => $addPlan->id,
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
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
            ],
        ]);

        $outlet = Outlet::visibleTo($user)->find($request->outlet_id);

        if (! $outlet) {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: outlet tidak ditemukan', [
                'user_id' => $user->id,
                'outlet_id' => $request->outlet_id,
            ]);

            throw new ResourceNotFoundException('Outlet tidak ditemukan');
        }

        $rangeStart = Carbon::createFromDate((int) $request->tahun, (int) $request->bulan, 1)->startOfMonth();
        $rangeEnd = $rangeStart->copy()->endOfMonth();

        $planVisit = PlanVisit::where('outlet_id', $outlet->id)
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
                'outlet_id' => $outlet->id,
                'bulan' => $request->bulan,
                'tahun' => $request->tahun,
            ]);

            throw new ResourceNotFoundException('Plan visit tidak ditemukan');
        }

        if ($planVisit->schedule_scope === 'weekly') {
            Log::channel('planvisit')->warning('Penghapusan plan visit gagal: jadwal mingguan tidak dapat dihapus', [
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'plan_visit_id' => $planVisit->id,
                'schedule_scope' => $planVisit->schedule_scope,
            ]);

            throw new BadRequestException('Plan visit mingguan tidak dapat dihapus');
        }

        $delete = PlanVisit::where('outlet_id', $outlet->id)
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
                'outlet_id' => $outlet->id,
                'bulan' => $request->bulan,
                'tahun' => $request->tahun,
            ]);

            throw new BadRequestException('Gagal menghapus plan visit');
        }

        Log::channel('planvisit')->info('Penghapusan plan visit berhasil', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
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
