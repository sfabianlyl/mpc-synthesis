<?php

namespace App\Filament\Resources\Conversations\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class SynthesisGroupAssignments extends RelationManager
{
    protected static string $relationship = 'synthesisGroupAssignments';

    protected static ?string $title = 'Synthesis Assignments';

    protected static ?string $pluralModelLabel = 'Synthesis Assignments';

    public function isHidden(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('group_id')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('group.name')
                    ->label('Group')
                    ->searchable()
                    ->toggleable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}