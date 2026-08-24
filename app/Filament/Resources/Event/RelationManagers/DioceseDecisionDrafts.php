<?php

namespace App\Filament\Resources\Event\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class DioceseDecisionDrafts extends RelationManager
{
    protected static string $relationship = 'dioceseDecisionDrafts';

    protected static ?string $title = 'Diocese Decision Drafts';

    protected static ?string $pluralModelLabel = 'Diocese Decision Drafts';

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
                Textarea::make('draft_text')
                    ->columnSpanFull(),
                Toggle::make('is_submitted'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}