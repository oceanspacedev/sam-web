<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function nooMounth(Request $request)
    {
        if ($request->daterangesearch) {
            $daterange = $request->daterangesearch;
            $month = date('m', strtotime($daterange));
            $monthName = date('F', strtotime($daterange));
            $year = date('Y', strtotime($daterange));
        } else {
            // Mengambil bulan kemarin beserta tahunnya
            $lastMonth = Carbon::now()->subMonth();
            $month = $lastMonth->format('m');
            $year = $lastMonth->year;
        }

        // mendapatkan jumlah hari dalam satu bulan
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $sundaysCount = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            // cek apakah hari minggu, minggu == 0
            if (date('w', mktime(0, 0, 0, $month, $day, $year)) == 0) {
                $sundaysCount++;
            }
        }

        // Jumlah minggu - jumlah hari dalam satu bulan
        $remainingDays = $daysInMonth - $sundaysCount;

        $jumlahDataNoo = DB::table('noos')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        $rataTambahNooPerHari = ceil(($jumlahDataNoo / $remainingDays) * 100) / 100;

        $jumlahDataPerPembuatNoo = DB::table('noos')
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('created_by')
            ->orderBy('total', 'desc')
            ->get();

        return view('report.noomounth', [
            'title' => 'Noo',
            'active' => 'report',
            'noomounth' => $jumlahDataNoo,
            'datanoomounth' => $jumlahDataPerPembuatNoo,
            'avgnooday' => $rataTambahNooPerHari,
            'monthName' => $monthName ?? null,
            'jumlahhari' => $remainingDays,
        ]);
    }
}
