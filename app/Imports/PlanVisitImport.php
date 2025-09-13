<?php

namespace App\Imports;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PlanVisitImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (strlen((string) preg_replace('/\s+/', '', $row['tanggal_visit'])) < 6) {
            throw new Exception('Tidak bisa import plan ada format import yang salah pastikan pada bagian tanggal format kolom tanggal_visit menggunakan text dan format tanggal yyyy-mm-dd');
        }

        if (Carbon::parse($row['tanggal_visit'])->weekOfYear <= now()->weekOfYear && now() > now()->startOfDay()->startOfWeek()->addDay(1)->addHour(10)) {
            throw new Exception('Tidak bisa import plan pada week ke '.Carbon::parse($row['tanggal_visit'])->weekOfYear.' sudah melebihi hari selasa tanggal '.now()->startOfDay()->startOfWeek()->addDay(1)->addHour(10)->format('d M y').' jam 10:00');
        }

        if (Carbon::parse($row['tanggal_visit'])->weekOfYear < now()->weekOfYear) {
            throw new Exception('Tidak bisa import plan kurang dari week '.now()->weekOfYear);
        }

        $user_id = User::where('nama_lengkap', $row['nama'])->first()->id;
        $outlet_id = Outlet::where('kode_outlet', preg_replace('/\s+/', '', $row['kode_outlet']))->first()->id;
        $tanggal_visit = Carbon::parse($row['tanggal_visit'])->format('Y-m-d');

        $planvisit = new PlanVisit;
        $planvisit = $planvisit->where('outlet_id', $outlet_id)->where('tanggal_visit', 'LIKE', "%{$tanggal_visit}%")->where('user_id', $user_id);
        if ($planvisit->first()) {
            $planvisit->update([
                'user_id' => $user_id,
                'outlet_id' => $outlet_id,
                'tanggal_visit' => $tanggal_visit,
            ]);
        } else {
            return new PlanVisit([
                'user_id' => $user_id,
                'outlet_id' => $outlet_id,
                'tanggal_visit' => $tanggal_visit,
            ]);
        }
    }
}
