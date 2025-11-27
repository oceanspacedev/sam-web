<?php

namespace App\Exports;

use App\Models\Register;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RegisterExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return Register::with(['cluster', 'region', 'badanusaha', 'createdBy', 'approvedBy', 'rejectedBy'])->get();
    }

    public function headings(): array
    {
        return [
            'tanggal dibuat',
            'dibuat oleh',
            'kode outlet',
            'badan usaha',
            'divisi',
            'nama outlet',
            'nama pemilik',
            'nomer KTP/NPWP',
            'alamat',
            'kota',
            'nomer outlet',
            'email',
            'region',
            'cluster',
            'limit',
            'status',
            'tanggal disetujui',
            'disetujui oleh',
            'tanggal ditolak',
            'ditolak oleh',
            'keterangan',
        ];
    }

    public function map($register): array
    {
        return [
            date('d M Y', strtotime($register->created_at)),
            $register->createdBy?->nama_lengkap ?? '-',
            $register->kode_outlet ?? '-',
            $register->badanusaha->name,
            $register->divisi->name,
            $register->nama_outlet,
            $register->nama_pemilik_outlet,
            $register->ktp_outlet,
            $register->alamat_outlet,
            $register->distric,
            $register->nomer_tlp_outlet,
            $register->nomer_wakil_outlet,
            $register->region->name ?? '-',
            $register->cluster->name ?? '-',
            'Rp '.number_format($register->limit, 0, ',', '.'),
            $register->status,
            $register->approved_at == null ? '-' : date('d M Y', strtotime($register->approved_at)),
            $register->approvedBy?->nama_lengkap ?? '-',
            $register->rejected_at == null ? '-' : date('d M Y', strtotime($register->rejected_at)),
            $register->rejectedBy?->nama_lengkap ?? '-',
            $register->keterangan ?? '-',
        ];
    }
}
