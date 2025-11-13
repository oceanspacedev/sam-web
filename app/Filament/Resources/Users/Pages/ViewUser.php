<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exports\UserMonthlyOutletsExport;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Maatwebsite\Excel\Facades\Excel;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        /** @var User $record */
        $record = $this->getRecord();

        return 'Detail User: '.$record->nama_lengkap;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('download_user_monthly_outlets')
                ->label('Download Rekap (Bulan saat ini)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    /** @var User $user */
                    $user = $this->getRecord();
                    $filename = 'user-outlets-report-'.$user->id.'-'.now()->format('Ym').'.xlsx';

                    return Excel::download(new UserMonthlyOutletsExport($user), $filename);
                }),
            Impersonate::make('impersonate')
                ->visible(fn (User $record): bool => (bool) $record->role?->can_access_web),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi User')
                    ->schema([
                        TextEntry::make('nama_lengkap')->label('Nama Lengkap'),
                        TextEntry::make('username')->label('Username'),
                        TextEntry::make('role.name')->label('Role'),
                        TextEntry::make('badanusaha.name')->label('Badan Usaha'),
                        TextEntry::make('divisi.name')->label('Divisi'),
                        TextEntry::make('region.name')->label('Region'),
                        TextEntry::make('cluster.name')->label('Cluster'),
                        TextEntry::make('tm.nama_lengkap')->label('TM'),
                    ])->columns(2),
            ]);
    }
}
