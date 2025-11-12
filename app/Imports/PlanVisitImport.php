<?php

namespace App\Imports;

use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PlanVisitImport implements ShouldQueue, ToModel, WithChunkReading, WithHeadingRow
{
    public function model(array $row)
    {
        $username = $this->requireString($row, 'username', 'username');
        $kodeOutlet = $this->requireString($row, 'kode_outlet', 'kode_outlet');
        $divisionName = $this->requireString($row, 'divisi', 'divisi');
        $tanggalVisitRaw = $this->requireString($row, 'tanggal_visit', 'tanggal_visit');

        if (strlen((string) preg_replace('/\s+/', '', $tanggalVisitRaw)) < 6) {
            throw new Exception('Tidak bisa import plan ada format import yang salah pastikan pada bagian tanggal format kolom tanggal_visit menggunakan text dan format tanggal yyyy-mm-dd');
        }

        $tanggal = Carbon::parse($tanggalVisitRaw);

        $minimumAllowedDate = now()->startOfDay()->addWeek();

        if ($tanggal->lt($minimumAllowedDate)) {
            throw new Exception('Tidak bisa import plan pada tanggal '.$tanggal->format('Y-m-d').' karena minimal harus satu minggu ke depan (>= '.$minimumAllowedDate->format('Y-m-d').').');
        }

        if ($tanggal->weekOfYear <= now()->weekOfYear && now() > now()->startOfDay()->startOfWeek()->addDay(1)->addHour(10)) {
            throw new Exception('Tidak bisa import plan pada week ke '.$tanggal->weekOfYear.' sudah melebihi hari selasa tanggal '.now()->startOfDay()->startOfWeek()->addDay(1)->addHour(10)->format('d M y').' jam 10:00');
        }

        $user = User::where('username', $username)->first();

        if (! $user) {
            throw new Exception('User dengan username '.$username.' tidak ditemukan.');
        }

        $division = Division::whereRaw('REPLACE(UPPER(name), " ", "") = ?', [$this->normalizeName($divisionName)])->first();

        if (! $division) {
            throw new Exception('Divisi '.$divisionName.' tidak ditemukan.');
        }

        $outlet = Outlet::query()
            ->where('divisi_id', $division->id)
            ->whereRaw('REPLACE(UPPER(kode_outlet), " ", "") = ?', [$this->normalizeOutletCode($kodeOutlet)])
            ->first();

        if (! $outlet) {
            throw new Exception('Outlet dengan kode '.$kodeOutlet.' pada divisi '.$divisionName.' tidak ditemukan.');
        }

        $tanggalVisit = $tanggal->format('Y-m-d');

        $planvisit = new PlanVisit;
        $planvisit = $planvisit->where('outlet_id', $outlet->id)
            ->whereDate('tanggal_visit', $tanggalVisit)
            ->where('user_id', $user->id);

        if ($existing = $planvisit->first()) {
            $existing->update([
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'tanggal_visit' => $tanggalVisit,
            ]);
        } else {
            return new PlanVisit([
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'tanggal_visit' => $tanggalVisit,
            ]);
        }
    }

    private function requireString(array $row, string $key, string $label): string
    {
        $value = $row[$key] ?? null;

        if ($value === null || trim((string) $value) === '') {
            throw new Exception('Kolom '.$label.' wajib diisi.');
        }

        return trim((string) $value);
    }

    private function normalizeOutletCode(string $value): string
    {
        return Str::upper(str_replace(' ', '', $value));
    }

    private function normalizeName(string $value): string
    {
        return Str::upper(str_replace(' ', '', $value));
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
