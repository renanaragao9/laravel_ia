<?php

namespace App\Filament\Resources\Draws\Tables;

use App\Filament\Filters\Draw\DrawnOnFilter;
use App\Filament\Filters\Draw\LotteryFilter;
use App\Models\Draw;
use App\Models\DrawResult;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DrawsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('results'))
            ->columns([
                TextColumn::make('drawn_on')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('lottery')
                    ->label('Loteria')
                    ->badge(),

                TextColumn::make('draw_time')
                    ->label('Horário')
                    ->placeholder('-'),

                TextColumn::make('results')
                    ->label('Milhares (1º ao 5º)')
                    ->state(fn (Draw $record) => $record->results->map(fn (DrawResult $result) => $result->thousand)->implode(' · ')),

                TextColumn::make('source')
                    ->label('Origem')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                LotteryFilter::make(),
                DrawnOnFilter::make(),
            ])
            ->defaultSort('drawn_on', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
