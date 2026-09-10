<?php

namespace App\Filament\Resources\NationalDecisionDraft\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class NationalDecisionDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('conversation.title')
                    ->label('Conversation')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('conversation.event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('diocese.name')
                    ->label('Diocese')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('draft_text')
                    ->label('Draft Text')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('is_submitted')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Submitted' : 'Draft')
                    ->colors([
                        'success' => true,
                        'gray' => false,
                    ])
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('conversation_id')
                    ->label('Conversation')
                    ->getTitleFromRecordUsing(fn ($record) => $record->conversation?->title ?? 'No Conversation'),
                Group::make('diocese_id')
                    ->label('Diocese')
                    ->getTitleFromRecordUsing(fn ($record) => $record->diocese?->name ?? 'No Diocese'),
            ])
            ->filters([
                SelectFilter::make('conversation_id')
                    ->label('Conversation')
                    ->relationship('conversation', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user_id')
                    ->label('Author')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('diocese_id')
                    ->label('Diocese')
                    ->relationship('diocese', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_submitted')
                    ->label('Submitted'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}