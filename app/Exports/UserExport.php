<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return User::with(['role','region','cluster','divisi','badanusaha'])->orderBy('nama_lengkap')->get();
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
            'cluster1',
            'cluster2',
            'tm',
        ];
    }

    public function map($user) : array
    {
        return [
            $user->nama_lengkap ?? ' ',
            $user->username ?? ' ',
            $user->role->name ?? ' ',
            $user->badanusaha->name ?? ' ',
            $user->divisi->name ?? ' ',
            $user->region->name ?? ' ',
            $user->cluster->name ?? ' ',
            $user->cluster2 ? $user->cluster2->name : ' ',
            $user->tm->nama_lengkap ?? ' ',
        ];
    }
}
