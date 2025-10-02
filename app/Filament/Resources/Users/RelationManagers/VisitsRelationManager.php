<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Visit;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visit';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_visit')
                    ->label('Tanggal Visit')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('user.nama_lengkap')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outlet.nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipe_visit')
                    ->label('Tipe Visit'),
                Tables\Columns\TextColumn::make('latlong_in')
                    ->label('Lokasi Check-In')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('latlong_out')
                    ->label('Lokasi Check-Out')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('check_in_time')
                    ->label('Jam Check-In')
                    ->time(),
                Tables\Columns\TextColumn::make('check_out_time')
                    ->label('Jam Check-Out')
                    ->time(),
                Tables\Columns\TextColumn::make('picture_visit_in')
                    ->label('Foto Check-In')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('picture_visit_out')
                    ->label('Foto Check-Out')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('transaksi')
                    ->label('Transaksi'),
                Tables\Columns\TextColumn::make('durasi_visit')
                    ->label('Durasi Visit'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], Visit::class)),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('tanggal_visit_from')
                            ->label('Tanggal Visit Mulai'),
                        DatePicker::make('tanggal_visit_until')
                            ->label('Tanggal Visit Akhir'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_visit_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal_visit', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_visit_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal_visit', '<=', $date),
                            );
                    }),

            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
