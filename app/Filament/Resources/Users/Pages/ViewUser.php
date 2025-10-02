<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Exports\UserMonthlyOutletsExport;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Maatwebsite\Excel\Facades\Excel;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        /** @var \App\Models\User $record */
        $record = $this->getRecord();

        return 'Detail User: '.$record->nama_lengkap;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('download_user_monthly_outlets')
                ->label('Download Rekap (Bulan saat ini)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    /** @var \App\Models\User $user */
                    $user = $this->getRecord();
                    $filename = 'user-outlets-report-'.$user->id.'-'.now()->format('Ym').'.xlsx';

                    return Excel::download(new UserMonthlyOutletsExport($user), $filename);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi User')
                    ->schema([
                        Infolists\Components\TextEntry::make('nama_lengkap')->label('Nama Lengkap'),
                        Infolists\Components\TextEntry::make('username')->label('Username'),
                        Infolists\Components\TextEntry::make('role.name')->label('Role'),
                        Infolists\Components\TextEntry::make('badanusaha.name')->label('Badan Usaha'),
                        Infolists\Components\TextEntry::make('divisi.name')->label('Divisi'),
                        Infolists\Components\TextEntry::make('region.name')->label('Region'),
                        Infolists\Components\TextEntry::make('cluster.name')->label('Cluster'),
                        Infolists\Components\TextEntry::make('tm.nama_lengkap')->label('TM'),
                    ])->columns(2),
            ]);
    }
}
