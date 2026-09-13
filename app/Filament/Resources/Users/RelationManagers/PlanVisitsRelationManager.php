<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\PlanVisit;
use App\Support\FilamentMorphVisitableSearch;
use App\Support\FilamentTableEagerLoad;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanVisitsRelationManager extends RelationManager
{
    protected static ?string $title = 'Plan Visit';

    protected static string $relationship = 'planvisit';

    protected function getTableQuery(): Builder
    {
        return PlanVisit::query()
            ->where('user_id', $this->getOwnerRecord()->id)
            ->with(FilamentTableEagerLoad::visitableTarget());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.nama_lengkap')
                    ->label('Nama')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('visitable.nama_outlet')
                    ->label('Outlet')
                    ->searchable(),
                TextColumn::make('visitable.kode_outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('period_start')
                    ->label('Tanggal Visit')
                    ->formatStateUsing(function ($state, PlanVisit $record) {
                        if ($record->isWeekly() && $record->period_start && $record->period_end) {
                            $start = Carbon::parse($record->period_start)->format('d M Y');
                            $end = Carbon::parse($record->period_end)->format('d M Y');

                            return $start.' - '.$end;
                        }

                        return $state ? Carbon::parse($state)->format('d M Y') : '-';
                    }),
                TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_visit', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->searchUsing(function (Builder $query, string $search): void {
                FilamentMorphVisitableSearch::apply($query, $search);
            })
            ->filters([
                //
            ])
            ->headerActions([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                ]),
            ]);
    }
}
