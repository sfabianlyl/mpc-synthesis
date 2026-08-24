<?php

namespace App\Filament\Resources\Conversations\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;

class Responses extends RelationManager
{
    protected static string $relationship = 'responses';

    protected static ?string $title = 'Responses';

    protected static ?string $pluralModelLabel = 'Responses';

    public function isHidden(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Textarea::make('consensus_text')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('conversationGroup.group.name')
                    ->label('Group')
                    ->searchable(),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}