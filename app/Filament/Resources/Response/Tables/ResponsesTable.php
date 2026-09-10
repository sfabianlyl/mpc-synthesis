<?php

namespace App\Filament\Resources\Response\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ResponsesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('conversation_group_id')
                    ->label('Conversation Group ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('conversationGroup.group.name')
                    ->label('Group Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('conversationGroup.conversation.title')
                    ->label('Conversation')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('consensus_text')
                    ->label('Consensus Text')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
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
                Group::make('conversation_group_id')
                    ->label('Conversation Group')
                    ->getTitleFromRecordUsing(fn ($record) => "Conversation Group #{$record->conversation_group_id}" . ($record->conversationGroup?->group?->name ? " - {$record->conversationGroup->group->name}" : '') . ($record->conversationGroup?->conversation?->title ? " ({$record->conversationGroup->conversation->title})" : '')),
            ])
            ->filters([
                SelectFilter::make('conversation_group_id')
                    ->label('Conversation Group')
                    ->relationship('conversationGroup', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "#{$record->id} - " . ($record->group?->name ?? 'Group') . " (" . ($record->conversation?->title ?? 'Conversation') . ")")
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('conversation_group_id', 'asc');
    }
}