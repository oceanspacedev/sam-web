<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exports\User\UserMonthlyOutletsExport;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
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
                ->label('Download Rekap Cycle User')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Select::make('month')
                        ->label('Bulan')
                        ->options([
                            1 => 'Januari',
                            2 => 'Februari',
                            3 => 'Maret',
                            4 => 'April',
                            5 => 'Mei',
                            6 => 'Juni',
                            7 => 'Juli',
                            8 => 'Agustus',
                            9 => 'September',
                            10 => 'Oktober',
                            11 => 'November',
                            12 => 'Desember',
                        ])
                        ->default((int) now()->format('m'))
                        ->required(),
                    Select::make('year')
                        ->label('Tahun')
                        ->options(function (): array {
                            $currentYear = (int) now()->format('Y');
                            $years = [];
                            for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
                                $years[$y] = (string) $y;
                            }

                            return $years;
                        })
                        ->default((int) now()->format('Y'))
                        ->required(),
                ])
                ->modalHeading('Download Rekap Cycle User')
                ->modalDescription('Pilih bulan dan tahun rekap cycle yang ingin diunduh.')
                ->modalSubmitActionLabel('Download Excel')
                ->action(function (array $data) {
                    /** @var User $user */
                    $user = $this->getRecord();
                    $month = (int) ($data['month'] ?? now()->format('m'));
                    $year = (int) ($data['year'] ?? now()->format('Y'));

                    $formattedPeriod = sprintf('%04d%02d', $year, $month);
                    $filename = 'user-cycle-report-'.$user->id.'-'.$formattedPeriod.'.xlsx';

                    return Excel::download(new UserMonthlyOutletsExport($user, $month, $year), $filename);
                }),
            Impersonate::make('impersonate')
                ->visible(fn (User $record): bool => (bool) $record->role?->can_access_web),
        ];
    }
}
