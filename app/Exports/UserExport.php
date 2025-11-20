<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @return Collection
     */
    public function collection()
    {
        return User::with(['role', 'regions', 'clusters', 'divisis', 'badanUsahas'])->orderBy('nama_lengkap')->get();
    }

    public function headings(): array
    {
        return [
            'nama_lengkap',
            'username',
            'role',
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
            'tm',
        ];
    }

    public function map($user): array
    {
        return [
            $user->nama_lengkap ?? ' ',
            $user->username ?? ' ',
            $user->role->name ?? ' ',
            $user->badanUsahas->pluck('name')->join(', ') ?: ' ',
            $user->divisis->pluck('name')->join(', ') ?: ' ',
            $user->regions->pluck('name')->join(', ') ?: ' ',
            $user->clusters->pluck('name')->join(', ') ?: ' ',
            $user->tm->nama_lengkap ?? ' ',
        ];
    }
}
