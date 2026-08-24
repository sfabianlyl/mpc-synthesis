<?php

namespace App\Filament\Resources\ConversationGroup\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConversationGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('conversation.title')
                    ->label('Conversation')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group.name')
                    ->label('Group')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('responses_count')
                    ->label('Responses')
                    ->counts('responses')
                    ->toggleable(),
                TextColumn::make('synthesisMembersCount')
                    ->label('Members')
                    ->counts('synthesisMembers')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}