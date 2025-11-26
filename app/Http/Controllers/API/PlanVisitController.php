<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanVisitResource;
use App\Models\Outlet;
use App\Models\PlanVisit;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlanVisitController extends Controller
{
    /**
     * Retrieve today's plan visits for the authenticated user
     *
     * Returns all plan visits scheduled for today's date for the currently
     * authenticated user. Includes complete relationship data for outlet
     * and user hierarchies with business entities, regions, clusters, and divisions.
     *
     * **Relationships included:**
     * - outlet.badanusaha: Business entity information
     * - outlet.region: Regional data
     * - outlet.divisi: Division data
     * - outlet.cluster: Cluster data
     * - user.badanusaha: User's business entity
     * - user.region: User's region
     * - user.divisi: User's division
     * - user.cluster: User's cluster
     * - user.role: User's role information
     *
     * **Data transformation:**
     * All results are transformed using PlanVisit::formatForAPI() which
     * converts dates to timestamps and includes loaded relationships.
     *
     * @response array{
     *   data: array{
     *     id: int,
     *     tanggal_visit: int|null,
     *     user_id: int,
     *     outlet_id: int,
     *     created_at: int|null,
     *     updated_at: int|null,
     *     user: array{id: int, nama_lengkap: string, username: string, ...}|null,
     *     outlet: array{id: int, kode_outlet: string, nama_outlet: string, ...}|null
     *   }[],
     *   message: string
     * }
     * @response 500 array{
     *   data: array{
     *     message: mixed
     *   },
     *   message: string
     * }
     */
    public function fetch(Request $request): JsonResponse
    {
        try {
            // If month and year are provided, filter by them (formerly bymonth)
            if ($request->has(['bulan', 'tahun'])) {
                $request->validate([
                    'bulan' => ['required', 'string'],
                    'tahun' => ['required', 'string'],
                ]);

                $rangeStart = Carbon::createFromDate((int) $request->tahun, (int) $request->bulan, 1)->startOfMonth();
                $rangeEnd = $rangeStart->copy()->endOfMonth();

                $plan = PlanVisit::with([
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
                    ->unrealized()
                    ->where('schedule_scope', 'daily')
                    ->whereBetween('period_start', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                    ->orderBy('period_start')
                    ->get();

                return PlanVisitResource::collection($plan)->additional([
                    'meta' => [
                        'code' => 200,
                        'status' => 'success',
                        'message' => 'berhasil',
                    ],
                    'errors' => null,
                ])->response();
            }

            // Default: Fetch today's plan visits
            $today = now()->toDateString();

            $planVisit = PlanVisit::with([
                'outlet.badanusaha',
                'outlet.region',
                'outlet.divisi',
                'outlet.cluster',
                'user.badanUsahas',
                'user.regions',
                'user.divisis',
                'user.clusters',
                'user.role',
            ])->where('user_id', Auth::user()->id)
                ->unrealized()
                ->where(function (Builder $builder) use ($today): void {
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

            return PlanVisitResource::collection($planVisit)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'ok',
                ],
                'errors' => null,
            ])->response();
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err->getMessage(),
            ], $err->getMessage(), 500);
        }
    }

    /**
     * Create a new plan visit with time-based validation
     *
     * Adds a new scheduled visit for the authenticated user with comprehensive
     * validation including Realme vs non-Realme time-window restrictions.
     * Prevents duplicate entries and enforces planning deadlines.
     *
     * **Validation rules:**
     * - tanggal_visit: Required date format
     * - kode_outlet: Required existing outlet code
     *
     * **Time-window validations:**
     * - **Realme division (divisi_id: 4)**: Cannot add plans after Tuesday 10 AM of the visit week
     * - **Non-Realme division**: Cannot add plans less than 3 days before visit date
     * - **Duplicate prevention**: Prevents multiple entries for same user/outlet/date
     *
     * **Realme vs non-Realme validation logic:**
     * - Realme: Weekly planning deadline (Tuesday 10 AM of visit week)
     * - Non-Realme: 3-day advance planning requirement (h-3 validation)
     *
     * **Error scenarios:**
     * - Outlet not found: 404 error
     * - Time window violation: 422 with specific message
     * - Duplicate entry: 422 with existing data
     *
     * @bodyParam tanggal_visit date required Visit date (YYYY-MM-DD format). Example: "2024-12-25"
     * @bodyParam kode_outlet string required Outlet unique identifier. Example: "OUTLET001"
     *
     * @response array{
     *   data: array{
     *     id: int,
     *     tanggal_visit: int|null,
     *     user_id: int,
     *     outlet_id: int,
     *     created_at: int|null,
     *     updated_at: int|null,
     *     user: array{id: int, nama_lengkap: string, username: string, ...}|null,
     *     outlet: array{id: int, kode_outlet: string, nama_outlet: string, ...}|null
     *   },
     *   message: string
     * }
     * @response 404 array{
     *   data: null,
     *   message: string
     * }
     * @response 422 array{
     *   data: mixed|null,
     *   message: string
     * }
     * @response 500 array{
     *   data: null,
     *   message: string
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            Log::channel('planvisit')->info('Plan visit add initiated', [
                'user_id' => $user->id,
                'payload' => [
                    'tanggal_visit' => $request->tanggal_visit,
                    'kode_outlet' => $request->kode_outlet,
                ],
            ]);

            $request->validate([
                'tanggal_visit' => ['required', 'date'],
                'kode_outlet' => ['required'],
            ]);

            $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();

            if (!$outlet) {
                Log::channel('planvisit')->warning('Plan visit add failed: outlet not found', [
                    'user_id' => $user->id,
                    'kode_outlet' => $request->kode_outlet,
                ]);

                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            // Check if user has Realme division (ID 4) using many-to-many relationship
            $userDivisiIds = $user->divisis()->pluck('divisions.id')->toArray();
            $isRealmeDivision = (in_array(4, $userDivisiIds) || $outlet->divisi_id == 4);
            $periodStart = Carbon::parse($request->tanggal_visit)->startOfDay();
            $schedulePayload = PlanVisit::schedulePayload($periodStart, 'daily');

            if (
                $isRealmeDivision
                && Carbon::now()->gt($periodStart->copy()->startOfWeek()->addDay()->setTime(10, 0))
            ) {
                Log::channel('planvisit')->warning('Plan visit add failed: weekly deadline passed', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'tanggal_visit' => $schedulePayload['period_start'],
                ]);

                return ResponseFormatter::error(null, 'Tidak bisa menambahkan plan visit kurang dari minggu yang berjalan');
            }

            if (
                !$isRealmeDivision
                && Carbon::now()->gt($periodStart->copy()->subDays(3))
            ) {
                Log::channel('planvisit')->warning('Plan visit add failed: H-3 deadline passed', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'tanggal_visit' => $schedulePayload['period_start'],
                ]);

                return ResponseFormatter::error(null, 'Tidak bisa menambahkan plan visit kurang dari h-3 visit');
            }

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

                return ResponseFormatter::error($existingPlan, 'data sebelumnya sudah ada');
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
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    /**
     * Delete plan visits by month/year and outlet for the authenticated user
     *
     * Removes all plan visits matching the specified month, year, and outlet
     * for the authenticated user. Performs validation to ensure data integrity
     * and proper authorization.
     *
     * **Validation rules:**
     * - bulan: Required string (month number)
     * - tahun: Required string (4-digit year)
     * - kode_outlet: Required string (existing outlet code)
     *
     * **Error scenarios:**
     * - Outlet not found: 404 error
     * - Validation failure: 422 error
     * - No records deleted: 422 error
     *
     * **Delete operation:**
     * Uses delete() to remove all matching records, not just first() result.
     * Returns count of deleted records on success.
     *
     * @bodyParam bulan string required Month number (01-12). Example: "12"
     * @bodyParam tahun string required 4-digit year. Example: "2024"
     * @bodyParam kode_outlet string required Outlet unique identifier. Example: "OUTLET001"
     *
     * @response array{
     *   data: int,
     *   message: string
     * }
     * @response 404 array{
     *   data: null,
     *   message: string
     * }
     * @response 422 array{
     *   data: null,
     *   message: string
     * }
     * @response 500 array{
     *   data: null,
     *   message: string
     * }
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            Log::channel('planvisit')->info('Plan visit delete initiated', [
                'user_id' => $user->id,
                'payload' => [
                    'bulan' => $request->bulan,
                    'tahun' => $request->tahun,
                    'kode_outlet' => $request->kode_outlet,
                ],
            ]);

            $validation = $request->validate([
                'bulan' => 'required',
                'tahun' => 'required',
                'kode_outlet' => 'required',
            ]);

            $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();

            if (!$outlet) {
                Log::channel('planvisit')->warning('Plan visit delete failed: outlet not found', [
                    'user_id' => $user->id,
                    'kode_outlet' => $request->kode_outlet,
                ]);

                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
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

            if (!$planVisit) {
                Log::channel('planvisit')->warning('Plan visit delete failed: plan not found', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'bulan' => $request->bulan,
                    'tahun' => $request->tahun,
                ]);

                return ResponseFormatter::error(null, 'Plan visit tidak ditemukan', 404);
            }

            if (!$validation) {
                return ResponseFormatter::error(null, $validation, 422);
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

            if (!$delete) {
                Log::channel('planvisit')->warning('Plan visit delete failed: no records deleted', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'bulan' => $request->bulan,
                    'tahun' => $request->tahun,
                ]);

                return ResponseFormatter::error(null, $validation, 422);
            }

            Log::channel('planvisit')->info('Plan visit delete success', [
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
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error(null, $e->getMessage(), 422);
        }
    }

    // deletenoo removed
    // deleterealme removed

    // No transformer required; models expose formatForAPI().
}
