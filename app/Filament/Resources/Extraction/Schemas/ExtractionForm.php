<?php

namespace App\Filament\Resources\Extraction\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExtractionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('conversation_id')
                    ->relationship('conversation', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Textarea::make('convergence_text')
                    ->columnSpanFull(),
                Textarea::make('consideration_text')
                    ->columnSpanFull(),
                Textarea::make('proposals_text')
                    ->columnSpanFull(),
                Toggle::make('is_successful'),
            ]);
    }
}