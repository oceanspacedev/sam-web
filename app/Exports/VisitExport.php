<?php

namespace App\Exports;

use App\Models\Visit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VisitExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    protected $tanggal1;

    protected $tanggal2;

    public function __construct($tanggal1, $tanggal2)
    {
        $this->tanggal1 = $tanggal1;
        $this->tanggal2 = date('Y-m-d 23:59:59', strtotime($tanggal2));
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        // Gunakan lazy() untuk memory efficiency
        return Visit::with(['user:id,nama_lengkap,role_id', 'user.role:id,name',
            'outlet:id,kode_outlet,nama_outlet,cluster_id,region_id,divisi_id',
            'outlet.cluster:id,name', 'outlet.region:id,name', 'outlet.divisi:id,name'])
            ->select(['id', 'tanggal_visit', 'user_id', 'outlet_id', 'tipe_visit',
                'picture_visit_in', 'picture_visit_out', 'latlong_in', 'latlong_out',
                'check_in_time', 'check_out_time', 'durasi_visit', 'transaksi', 'laporan_visit'])
            ->whereBetween('tanggal_visit', [$this->tanggal1, $this->tanggal2])
            ->lazy(); // Menggunakan lazy loading untuk memory efficiency
    }

    public function headings(): array
    {
        return [
            'tanggal',
            'nama',
            'role',
            'kode_outlet',
            'outlet',
            'divisi',
            'region',
            'cluster',
            'tipe',
            'Foto CI',
            'Foto CO',
            'lokasi CI',
            'lokasi CO',
            'jam CI',
            'jam CO',
            'durasi',
            'transaksi',
            'laporan',
        ];
    }

    public function map($visit): array
    {
        return [
            date('d M Y', strtotime($visit->tanggal_visit)),
            $visit->user->nama_lengkap ?? ' ',
            $visit->user->role->name ?? ' ',
            $visit->outlet->kode_outlet ?? ' ',
            $visit->outlet->nama_outlet ?? ' ',
            $visit->outlet->divisi->name ?? ' ',
            $visit->outlet->region->name ?? ' ',
            $visit->outlet->cluster->name ?? ' ',
            $visit->tipe_visit ?? ' ',
            $visit->picture_visit_in ? 'https://grosir.mediaselularindonesia.com/storage/'.$visit->picture_visit_in : ' ',
            $visit->picture_visit_out ? 'https://grosir.mediaselularindonesia.com/storage/'.$visit->picture_visit_out : ' ',
            $visit->latlong_in ? 'https://www.google.com/maps/place/'.$visit->latlong_in : ' ',
            $visit->latlong_out ? 'https://www.google.com/maps/place/'.$visit->latlong_out : ' ',
            $visit->check_in_time ? date('H:i', strtotime($visit->check_in_time)) : ' ',
            $visit->check_out_time ? date('H:i', strtotime($visit->check_out_time)) : ' ',
            $visit->durasi_visit ? $visit->durasi_visit.' Menit' : ' ',
            $visit->transaksi ?? ' ',
            $visit->laporan_visit ?? ' ',
        ];
    }
}
