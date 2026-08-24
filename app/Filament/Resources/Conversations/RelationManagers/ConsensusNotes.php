<?php

namespace App\Filament\Resources\Conversations\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class ConsensusNotes extends RelationManager
{
    protected static string $relationship = 'consensusNotes';

    protected static ?string $title = 'Consensus Notes';

    protected static ?string $pluralModelLabel = 'Consensus Notes';

    public function isHidden(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Textarea::make('text')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}