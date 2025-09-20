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

class PlanVisitController extends Controller
{
    /**
     * Return today's plan visits for the authenticated user.
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
     * Return plan visits for the given month/year for the authenticated user.
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
     * Add a new plan visit for the authenticated user.
     */
    public function add(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tanggal_visit' => ['required', 'date'],
                'kode_outlet' => ['required'],
            ]);

            $idOutlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();

            if (! $idOutlet) {
                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            // VALIDASI
            // Kalau Realme bisa input plan visit mingguan, mulai dari sabtu sampai maks selasa jam 10
            // if ((Auth::user()->divisi_id == 4 || $idOutlet->divisi_id == 4) && (Carbon::now() > Carbon::parse($request->tanggal_visit)->startOfWeek()->addDay(1)->addHour(10))) {
            //    return ResponseFormatter::error(null,'Tidak bisa menambahkan plan visit kurang dari minggu yang berjalan');
            // } else if ((Auth::user()->divisi_id != 4 && $idOutlet->divisi_id != 4) && ((Carbon::now() > Carbon::parse($request->tanggal_visit)->startOfMonth()) || (Carbon::now() < Carbon::parse($request->tanggal_visit)->startOfMonth()->subDay(5)))){
            //     return ResponseFormatter::error(null,'Tidak bisa menambahkan plan visit kurang dari h-5 bulan visit dan lebih dari tanggal 1');
            // }

            if ((Auth::user()->divisi_id == 4 || $idOutlet->divisi_id == 4) && (Carbon::now() > Carbon::parse($request->tanggal_visit)->startOfWeek()->addDay(1)->addHour(10))) {
                return ResponseFormatter::error(null, 'Tidak bisa menambahkan plan visit kurang dari minggu yang berjalan');
            } elseif ((Auth::user()->divisi_id != 4 && $idOutlet->divisi_id != 4) && ((Carbon::now() > Carbon::parse($request->tanggal_visit)->addDay(3)))) {
                return ResponseFormatter::error(null, 'Tidak bisa menambahkan plan visit kurang dari h-3 visit');
            }

            // tanggal skrg kurang dari tgl 1
            // dd(Carbon::now() < Carbon::parse($request->tanggal_visit)->startOfMonth()); //true
            // tanggal skrg lebih dari h-5
            // dd(Carbon::now() > Carbon::parse($request->tanggal_visit)->startOfMonth()->subDay(5)); //false

            // tanggal skrg lebih dari tgl 1
            // dd(Carbon::now() > Carbon::parse($request->tanggal_visit)->startOfMonth()); //true
            // tanggal skrg kurang dari h-5
            // dd(Carbon::now() < Carbon::parse($request->tanggal_visit)->startOfMonth()->subDay(5)); //false

            // #cek apakah sudah ada data dengan user, outlet dan tanggal yang dikirim
            $cekData = PlanVisit::whereDate('tanggal_visit', Carbon::parse($request->tanggal_visit))
                ->where('user_id', Auth::user()->id)
                ->where('outlet_id', $idOutlet->id)
                ->first();
            if ($cekData) {
                return ResponseFormatter::error($cekData, 'data sebelumnya sudah ada');
            }
            $addPlan = PlanVisit::create([
                'user_id' => Auth::id(),
                'outlet_id' => $idOutlet->id,
                'tanggal_visit' => Carbon::parse($request->tanggal_visit),
            ]);

            // Load relations to match fetch/bymonth payload shape
            $addPlan->load([
                'outlet.badanusaha',
                'outlet.region',
                'outlet.divisi',
                'outlet.cluster',
                'user.badanusaha',
                'user.region',
                'user.divisi',
                'user.cluster',
                'user.role',
            ]);

            return ResponseFormatter::success($addPlan->formatForAPI(), 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage());
        }

    }

    /**
     * Delete plan visits by month/year and outlet for the authenticated user.
     */
    public function delete(Request $request): JsonResponse
    {
        try {
            $validation = $request->validate([
                'bulan' => 'required',
                'tahun' => 'required',
                'kode_outlet' => 'required',
            ]);

            $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();

            // Untuk validasi pake first() berarti cuma keambil 1 data
            $planVisit = PlanVisit::where('outlet_id', $outlet->id)
                ->whereYear('tanggal_visit', '=', $request->tahun)
                ->whereMonth('tanggal_visit', '=', $request->bulan)
                ->where('user_id', Auth::user()->id)
                ->first();

            // if ((Carbon::now() > Carbon::createFromTimestamp($planVisit->tanggal_visit )->startOfMonth()) || (Carbon::now() < Carbon::createFromTimestamp($planVisit->tanggal_visit )->startOfMonth()->subDay(5))){
            //     return ResponseFormatter::error(null,'Tidak bisa menghapus plan visit kurang dari h-5 bulan visit dan lebih dari tanggal 1');
            // }

            if (! $validation) {
                return ResponseFormatter::error(null, $validation, 422);
            }

            // sedangkan delete nya pake delete(), berarti semua PlanVisit yang id_outletnya sesuai akan terhapus
            $delete = PlanVisit::where('outlet_id', $outlet->id)
                ->whereYear('tanggal_visit', $request->tahun)
                ->whereMonth('tanggal_visit', $request->bulan)
                ->where('user_id', Auth::user()->id)
                ->delete();

            if (! $delete) {
                return ResponseFormatter::error(null, $validation, 422);
            }

            return ResponseFormatter::success($delete, 'berhasil');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error(null, $e->getMessage(), 422);
        }
    }

    /**
     * Delete a single plan visit by id for Realme flow.
     */
    public function deleterealme(Request $request): JsonResponse
    {
        try {
            $validation = $request->validate([
                'id' => 'required',
            ]);

            $planVisit = PlanVisit::where('id', $request->id)
                ->where('user_id', Auth::user()->id)
                ->first();

            if ((Carbon::now() > Carbon::createFromTimestamp($planVisit->tanggal_visit)->startOfWeek()->addDay(1)->addHour(10))) {
                return ResponseFormatter::error(null, 'Tidak bisa menghapus plan visit kurang dari atau dalam minggu yang berjalan');
            }

            if (! $validation) {
                return ResponseFormatter::error(null, $validation, 422);
            }

            $delete = PlanVisit::where('id', $request->id)
                ->where('user_id', Auth::user()->id)
                ->delete();

            if (! $delete) {
                return ResponseFormatter::error(null, $validation, 422);
            }

            return ResponseFormatter::success($delete, 'berhasil');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error(null, $e->getMessage(), 422);

        }
    }

    // deletenoo removed

    // No transformer required; models expose formatForAPI().
}
