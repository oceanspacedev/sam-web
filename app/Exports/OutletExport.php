<?php

namespace App\Exports;

use App\Models\Outlet;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OutletExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * Mengambil data outlet dengan eager loading dan chunking
     */
    public function query()
    {
        return Outlet::with(['cluster', 'region', 'badanusaha'])
            ->orderBy('nama_outlet');
    }

    /**
     * Mendefinisikan headings untuk export Excel
     */
    public function headings(): array
    {
        return [
            'badan_usaha', 'divisi', 'region', 'cluster', 'kode_outlet', 'nama_outlet', 'alamat_outlet',
            'distric', 'status_outlet', 'status', 'radius', 'limit', 'latlong', 'nama_pemilik_outlet', 'telepon_outlet',
            'TM', 'ASC', 'DSF', 'tanggal registrasi', 'foto_shop_sign', 'foto_depan', 'foto_kiri',
            'foto_kanan', 'foto_ktp', 'video',
        ];
    }

    /**
     * Mendapatkan data outlet dalam bentuk array untuk diexport
     */
    public function map($outlet): array
    {
        // Base URL untuk foto dan video
        $baseUrl = 'http://grosir.mediaselularindonesia.com/storage/';

        // Foto dan video
        $fotoFields = ['poto_shop_sign', 'poto_depan', 'poto_kiri', 'poto_kanan', 'poto_ktp', 'video'];
        $mappedData = [];

        foreach ($fotoFields as $field) {
            $mappedData[$field] = $outlet->$field ? $baseUrl.$outlet->$field : '-';
        }

        // Ambil data user untuk TM, ASC, DSF
        $tm = $this->getUserByRole($outlet, 2, 'tm');
        $asc = $this->getUserByRole($outlet, 2);
        $dsf = $this->getUserByRole($outlet, 3, 'cluster');

        // Format tanggal
        $tanggalRegistrasi = Carbon::parse($outlet->created_at)->format('d M Y');

        return [
            $outlet->badanusaha ? $outlet->badanusaha->name : 'No Business',
            $outlet->divisi ? $outlet->divisi->name : 'No Division',
            $outlet->region ? $outlet->region->name : 'No Region',
            $outlet->cluster ? $outlet->cluster->name : 'No Cluster',
            $outlet->kode_outlet,
            $outlet->nama_outlet,
            $outlet->alamat_outlet,
            $outlet->distric,
            $outlet->is_member ? 'MEMBER' : 'LEAD',
            $outlet->status_outlet,
            $outlet->radius.' Meter',
            'Rp '.number_format($outlet->limit, 0, ',', '.'),
            $outlet->latlong,
            $outlet->nama_pemilik_outlet,
            $outlet->nomer_tlp_outlet,
            $tm,  // Nama TM
            $asc, // Nama ASC
            $dsf, // Nama DSF
            $tanggalRegistrasi,
            $mappedData['poto_shop_sign'],
            $mappedData['poto_depan'],
            $mappedData['poto_kiri'],
            $mappedData['poto_kanan'],
            $mappedData['poto_ktp'],
            $mappedData['video'],
        ];
    }

    /**
     * Fungsi untuk mendapatkan nama user berdasarkan role_id
     */
    private function getUserByRole($outlet, $roleId, $relation = null)
    {
        $userQuery = User::where('divisi_id', $outlet->divisi_id)
            ->where('region_id', $outlet->region_id)
            ->where('role_id', $roleId);

        // Jika ada cluster_id untuk role_id 3
        if ($roleId == 3) {
            $userQuery->where('cluster_id', $outlet->cluster_id);
        }

        $user = $userQuery->first();

        if ($user) {
            return $relation && isset($user->$relation) ? $user->$relation->nama_lengkap : $user->nama_lengkap;
        }

        return 'VACANT';  // Kembalikan 'VACANT' jika tidak ada user
    }
}
