<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\PlanVisit;
use Carbon\Carbon;
use Exception;
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
            $planVisit = PlanVisit::with([
                'outlet.badanusaha',
                'outlet.region',
                'outlet.divisi',
                'outlet.cluster',
                'user.badanusaha',
                'user.region',
                'user.divisi',
                'user.cluster',
                'user.role',
            ])->where('user_id', Auth::user()->id)
                ->whereDate('tanggal_visit', date('Y-m-d'))
                ->get();

            return ResponseFormatter::success(
                $planVisit->map->formatForAPI(),
                'ok'
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], $err, 500);
        }
    }

    /**
     * Retrieve plan visits filtered by month and year for the authenticated user
     *
     * Returns plan visits for a specific month and year combination, allowing
     * users to view their scheduled visits for any historical or future period.
     * Results are ordered by visit date and include complete relationship data.
     *
     * **Validation rules:**
     * - bulan: Required string (month number, 01-12)
     * - tahun: Required string (4-digit year)
     *
     * **Relationships included:**
     * Complete outlet and user hierarchy data including business entities,
     * regions, clusters, divisions, and role information.
     *
     * **Data transformation:**
     * All results use PlanVisit::formatForAPI() with timestamp conversion
     * and loaded relationships for API consistency.
     *
     * @bodyParam bulan string required Month number (01-12). Example: "12"
     * @bodyParam tahun string required 4-digit year. Example: "2024"
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
     * @response 422 array{
     *   data: null,
     *   message: string
     * }
     * @response 500 array{
     *   data: null,
     *   message: string
     * }
     */
    public function bymonth(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'bulan' => ['required', 'string'],
                'tahun' => ['required', 'string'],
            ]);

            $plan = PlanVisit::with([
                'outlet.badanusaha',
                'outlet.region',
                'outlet.divisi',
                'outlet.cluster',
                'user.badanusaha',
                'user.region',
                'user.divisi',
                'user.cluster',
                'user.role',
            ])->whereYear('tanggal_visit', '=', $request->tahun)
                ->whereMonth('tanggal_visit', '=', $request->bulan)
                ->where('user_id', Auth::user()->id)
                ->orderBy('tanggal_visit')
                ->get();

            return ResponseFormatter::success(
                $plan->map->formatForAPI(),
                'berhasil'
            );
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e);
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
    public function add(Request $request): JsonResponse
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

            if (! $outlet) {
                Log::channel('planvisit')->warning('Plan visit add failed: outlet not found', [
                    'user_id' => $user->id,
                    'kode_outlet' => $request->kode_outlet,
                ]);

                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            if (($user->divisi_id == 4 || $outlet->divisi_id == 4)
                && Carbon::now()->gt(Carbon::parse($request->tanggal_visit)->startOfWeek()->addDay(1)->addHour(10))) {
                Log::channel('planvisit')->warning('Plan visit add failed: weekly deadline passed', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'tanggal_visit' => $request->tanggal_visit,
                ]);

                return ResponseFormatter::error(null, 'Tidak bisa menambahkan plan visit kurang dari minggu yang berjalan');
            }

            if (($user->divisi_id != 4 && $outlet->divisi_id != 4)
                && Carbon::now()->gt(Carbon::parse($request->tanggal_visit)->subDays(3))) {
                Log::channel('planvisit')->warning('Plan visit add failed: H-3 deadline passed', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'tanggal_visit' => $request->tanggal_visit,
                ]);

                return ResponseFormatter::error(null, 'Tidak bisa menambahkan plan visit kurang dari h-3 visit');
            }

            $existingPlan = PlanVisit::whereDate('tanggal_visit', Carbon::parse($request->tanggal_visit))
                ->where('user_id', $user->id)
                ->where('outlet_id', $outlet->id)
                ->first();

            if ($existingPlan) {
                Log::channel('planvisit')->warning('Plan visit add failed: duplicate', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'tanggal_visit' => $request->tanggal_visit,
                ]);

                return ResponseFormatter::error($existingPlan, 'data sebelumnya sudah ada');
            }

            $addPlan = PlanVisit::create([
                'user_id' => (string) $user->id,
                'outlet_id' => $outlet->id,
                'tanggal_visit' => Carbon::parse($request->tanggal_visit),
            ]);

            Log::channel('planvisit')->info('Plan visit add success', [
                'plan_visit_id' => $addPlan->id,
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'tanggal_visit' => $addPlan->tanggal_visit,
            ]);

            return ResponseFormatter::success($addPlan, 'berhasil');
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

            if (! $outlet) {
                Log::channel('planvisit')->warning('Plan visit delete failed: outlet not found', [
                    'user_id' => $user->id,
                    'kode_outlet' => $request->kode_outlet,
                ]);

                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            $planVisit = PlanVisit::where('outlet_id', $outlet->id)
                ->whereYear('tanggal_visit', '=', $request->tahun)
                ->whereMonth('tanggal_visit', '=', $request->bulan)
                ->where('user_id', $user->id)
                ->first();

            if (! $planVisit) {
                Log::channel('planvisit')->warning('Plan visit delete failed: plan not found', [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'bulan' => $request->bulan,
                    'tahun' => $request->tahun,
                ]);

                return ResponseFormatter::error(null, 'Plan visit tidak ditemukan', 404);
            }

            if (! $validation) {
                return ResponseFormatter::error(null, $validation, 422);
            }

            $delete = PlanVisit::where('outlet_id', $outlet->id)
                ->whereYear('tanggal_visit', $request->tahun)
                ->whereMonth('tanggal_visit', $request->bulan)
                ->where('user_id', $user->id)
                ->delete();

            if (! $delete) {
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

            return ResponseFormatter::success($delete, 'berhasil');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error(null, $e->getMessage(), 422);
        }
    }

    /**
     * Delete single plan visit by ID for Realme division with week-based validation
     *
     * Deletes a specific plan visit for Realme division users with time-based
     * validation. Only allows deletion before the weekly deadline (Tuesday 10 AM)
     * to maintain the weekly planning structure.
     *
     * **Realme-specific validation:**
     * - Cannot delete plan visits after Tuesday 10 AM of the visit week
     * - Ensures weekly planning integrity
     *
     * @bodyParam id int required Plan visit unique identifier. Example: 123
     *
     * @response array{
     *   data: int,
     *   message: string
     * }
     * @response 422 array{
     *   data: null,
     *   message: string
     * }
     */
    public function deleterealme(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            Log::channel('planvisit')->info('Plan visit delete realme initiated', [
                'user_id' => $user->id,
                'plan_visit_id' => $request->id,
            ]);

            $validation = $request->validate([
                'id' => 'required',
            ]);

            $planVisit = PlanVisit::where('id', $request->id)
                ->where('user_id', $user->id)
                ->first();

            if ((Carbon::now() > Carbon::createFromTimestamp($planVisit->tanggal_visit)->startOfWeek()->addDay(1)->addHour(10))) {
                Log::channel('planvisit')->warning('Plan visit delete realme failed: deadline passed', [
                    'user_id' => $user->id,
                    'plan_visit_id' => $request->id,
                ]);

                return ResponseFormatter::error(null, 'Tidak bisa menghapus plan visit kurang dari atau dalam minggu yang berjalan');
            }

            if (! $validation) {
                return ResponseFormatter::error(null, $validation, 422);
            }

            $delete = PlanVisit::where('id', $request->id)
                ->where('user_id', $user->id)
                ->delete();

            if (! $delete) {
                Log::channel('planvisit')->warning('Plan visit delete realme failed: record not found', [
                    'user_id' => $user->id,
                    'plan_visit_id' => $request->id,
                ]);

                return ResponseFormatter::error(null, $validation, 422);
            }

            Log::channel('planvisit')->info('Plan visit delete realme success', [
                'user_id' => $user->id,
                'plan_visit_id' => $request->id,
            ]);

            return ResponseFormatter::success($delete, 'berhasil');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error(null, $e->getMessage(), 422);

        }
    }

    // deletenoo removed

    // No transformer required; models expose formatForAPI().
}
