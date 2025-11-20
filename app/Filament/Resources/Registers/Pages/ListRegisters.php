<?php

namespace App\Filament\Resources\Registers\Pages;

use App\Filament\Exports\RegisterExporter;
use App\Filament\Resources\Registers\RegisterResource;
use App\Models\Register;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListRegisters extends ListRecords
{
    protected static string $resource = RegisterResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make(),
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
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'PENDING')->where(fn ($q) => $q->whereNull('keterangan')->orWhere('keterangan', '!=', 'LEAD')))
                ->badge($this->getStatusBadgeCount($query, 'PENDING', true))
                ->badgeColor('warning'),

            'confirmed' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'CONFIRMED')->where(fn ($q) => $q->whereNull('keterangan')->orWhere('keterangan', '!=', 'LEAD')))
                ->badge($this->getStatusBadgeCount($query, 'CONFIRMED', true))
                ->badgeColor('info'),

            'approved' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'APPROVED')->where(fn ($q) => $q->whereNull('keterangan')->orWhere('keterangan', '!=', 'LEAD')))
                ->badge($this->getStatusBadgeCount($query, 'APPROVED', true))
                ->badgeColor('success'),

            'rejected' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'REJECTED')->where(fn ($q) => $q->whereNull('keterangan')->orWhere('keterangan', '!=', 'LEAD')))
                ->badge($this->getStatusBadgeCount($query, 'REJECTED', true))
                ->badgeColor('danger'),

            'lead' => Tab::make()
                ->label('LEAD')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'PENDING')->where('keterangan', 'LEAD'))
                ->badge($query->clone()->where('status', 'PENDING')->where('keterangan', 'LEAD')->count())
                ->badgeColor('primary'),
        ];
    }

    // Fungsi untuk menghitung jumlah berdasarkan status dengan filter yang sudah diterapkan
    private function getStatusBadgeCount(Builder $query, string $status, bool $excludeLead = false): int
    {
        $q = $query->clone()->where('status', $status);

        if ($excludeLead) {
            $q->where(fn ($sq) => $sq->whereNull('keterangan')->orWhere('keterangan', '!=', 'LEAD'));
        }

        return $q->count();
    }
}
