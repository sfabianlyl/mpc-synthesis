<?php

namespace App\Filament\Resources\SynthesisKeyPoint\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SynthesisKeyPointForm
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
                Select::make('master_synthesis_id')
                    ->relationship('masterSynthesis', 'id')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('source_category')
                    ->maxLength(100),
                Textarea::make('key_points_text')
                    ->columnSpanFull(),
                TextInput::make('requested_count')
                    ->numeric()
                    ->minValue(1),
            ]);
    }
}