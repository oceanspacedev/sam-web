<?php

namespace App\Filament\Resources\RegisterResource\Pages;

use App\Filament\Exports\RegisterExporter;
use App\Filament\Resources\RegisterResource;
use App\Models\Register;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListRegisters extends ListRecords
{
    protected static string $resource = RegisterResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Check if the user is authorized to export
    if (Gate::allows('export', Register::class)) {
            $actions[] = ExportAction::make()
        ->exporter(RegisterExporter::class)
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->label('Export');
        }

        return $actions;
    }

    public function getTabs(): array
    {
        // Ambil query yang sudah difilter berdasarkan role
    $query = RegisterResource::getEloquentQuery(); // Panggil getEloquentQuery() dari Resource

        return [
            'pending' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'PENDING'))
                ->badge($this->getStatusBadgeCount($query, 'PENDING'))
                ->badgeColor('warning'),

            'confirmed' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'CONFIRMED'))
                ->badge($this->getStatusBadgeCount($query, 'CONFIRMED'))
                ->badgeColor('info'),

            'approved' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'APPROVED'))
                ->badge($this->getStatusBadgeCount($query, 'APPROVED'))
                ->badgeColor('success'),

            'rejected' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'REJECTED'))
                ->badge($this->getStatusBadgeCount($query, 'REJECTED'))
                ->badgeColor('danger'),
        ];
    }

    // Fungsi untuk menghitung jumlah berdasarkan status dengan filter yang sudah diterapkan
    private function getStatusBadgeCount(Builder $query, string $status): int
    {
        return $query->clone()->where('status', $status)->count();
    }
}