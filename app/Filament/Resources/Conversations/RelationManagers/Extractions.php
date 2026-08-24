<?php

namespace App\Filament\Resources\Conversations\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class Extractions extends RelationManager
{
    protected static string $relationship = 'extractions';

    protected static ?string $title = 'Extractions';

    protected static ?string $pluralModelLabel = 'Extractions';

    public function isHidden(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Textarea::make('convergence_text')
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