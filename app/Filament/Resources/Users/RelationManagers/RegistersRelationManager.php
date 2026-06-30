<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Register;
use App\Models\User;
use App\Support\FilamentTableEagerLoad;
use App\Support\StorageDisk;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class RegistersRelationManager extends RelationManager
{
    protected static ?string $title = 'Register';

    protected static string $relationship = 'registers';

    protected function getTableQuery(): Builder
    {
        /** @var User $owner */
        $owner = $this->getOwnerRecord();

        return Register::query()
            ->with(FilamentTableEagerLoad::registerHierarchy())
            ->with('tm:id,nama_lengkap')
            ->where(function (Builder $query) use ($owner): void {
                $query->where('created_by_id', $owner->id)
                    ->orWhere('tm_id', $owner->id);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_outlet')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'LEAD' => 'warning',
                        'NOO' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'PENDING' => 'warning',
                        'CONFIRMED' => 'info',
                        'APPROVED' => 'success',
                        'REJECTED' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-'),
                TextColumn::make('kode_outlet')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('relation_to_user')
                    ->label('Relasi')
                    ->state(function (Register $record): string {
                        /** @var User $owner */
                        $owner = $this->getOwnerRecord();
                        $labels = [];

                        if ((int) $record->created_by_id === (int) $owner->id) {
                            $labels[] = 'Dibuat';
                        }

                        if ((int) $record->tm_id === (int) $owner->id) {
                            $labels[] = 'TM';
                        }

                        return implode(' + ', $labels) ?: '-';
                    })
                    ->badge(),
                TextColumn::make('createdBy.nama_lengkap')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('tm.nama_lengkap')
                    ->label('TM')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('divisi.name')
                    ->label('Divisi')
                    ->toggleable(),
                TextColumn::make('region.name')
                    ->label('Region')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cluster.name')
                    ->label('Cluster')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latlong')
                    ->label('Lokasi')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString(filled($state) ? 'LOKASI' : '-'))
                    ->url(fn (?string $state): ?string => filled($state) ? 'https://www.google.com/maps/place/'.$state : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_shop_sign')
                    ->label('Foto Toko')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString(filled($state) ? 'FOTO' : '-'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Update')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options([
                        'LEAD' => 'LEAD',
                        'NOO' => 'NOO',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'PENDING' => 'PENDING',
                        'CONFIRMED' => 'CONFIRMED',
                        'APPROVED' => 'APPROVED',
                        'REJECTED' => 'REJECTED',
                    ]),
                TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['RestoreAny:Register', 'ForceDeleteAny:Register'], Register::class)),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),
                ]),
            ]);
    }
}
