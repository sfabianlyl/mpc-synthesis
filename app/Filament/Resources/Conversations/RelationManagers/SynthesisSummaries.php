<?php

namespace App\Filament\Resources\Conversations\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

class SynthesisSummaries extends RelationManager
{
    protected static string $relationship = 'synthesisSummaries';

    protected static ?string $title = 'Synthesis Summaries';

    protected static ?string $pluralModelLabel = 'Synthesis Summaries';

    public function isHidden(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Textarea::make('convergence_summary')
                    ->label('Convergence')
                    ->columnSpanFull()
                    ->rows(3),
                Textarea::make('consideration_summary')
                    ->label('Consideration')
                    ->columnSpanFull()
                    ->rows(3),
                Textarea::make('proposals_summary')
                    ->label('Proposals')
                    ->columnSpanFull()
                    ->rows(3),
                Toggle::make('is_successful'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                ToggleColumn::make('is_successful'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}