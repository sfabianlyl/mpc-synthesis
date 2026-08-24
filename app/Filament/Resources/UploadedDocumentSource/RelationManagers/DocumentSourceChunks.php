<?php

namespace App\Filament\Resources\UploadedDocumentSource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;

class DocumentSourceChunks extends RelationManager
{
    protected static string $relationship = 'chunks';

    protected static ?string $title = 'Chunks';

    protected static ?string $pluralModelLabel = 'Chunks';

    public function isHidden(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('chunk_index')
                    ->numeric()
                    ->required(),
                Textarea::make('chunk_content')
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->maxLength(255),
                TextInput::make('section_heading')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('chunk_index')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('section_heading')
                    ->toggleable(isToggledHiddenByDefault: true),
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