<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\PostStatus;
use App\Filament\Actions\PublishPostAction;
use App\Filament\Actions\UnpublishPostAction;
use App\Filament\Filters\Post\StatusFilter;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('post_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Título')
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn (PostStatus $state) => $state === PostStatus::Published ? 'success' : 'warning'),

                TextColumn::make('model')
                    ->label('Modelo')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('Publicado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                StatusFilter::make(),
            ])
            ->defaultSort('post_date', 'desc')
            ->recordActions([
                PublishPostAction::make(),

                UnpublishPostAction::make(),

                Action::make('open')
                    ->label('Ver no blog')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Post $record) => route('blog.show', $record->slug), shouldOpenInNewTab: true)
                    ->visible(fn (Post $record) => $record->status === PostStatus::Published),

                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
