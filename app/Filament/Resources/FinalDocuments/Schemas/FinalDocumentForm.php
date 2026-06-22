<?php

namespace App\Filament\Resources\FinalDocuments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class FinalDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('content_json')
                    ->columnSpanFull()
                    ->label('Content (JSON)')
                    ->rows(10),
                Textarea::make('emerging_themes')
                    ->columnSpanFull(),
                TextInput::make('exported_file_path')
                    ->maxLength(500),
                Select::make('created_by')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('conversations')
                    ->relationship('conversations', 'title')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->label('Included Conversations'),
            ]);
    }
}
