<?php

namespace App\Filament\Imports;

use App\Models\Outlet;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class OutletImporter extends Importer
{
    protected static ?string $model = Outlet::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('badanusaha')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('divisi')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('region')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('cluster')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('kode_outlet')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('nama_outlet')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('alamat_outlet')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('distric')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('status_outlet')
                ->requiredMapping()
                ->rules(['required']),
        ];
    }

    public function resolveRecord(): ?Outlet
    {
        // return Outlet::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new Outlet;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your outlet import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
