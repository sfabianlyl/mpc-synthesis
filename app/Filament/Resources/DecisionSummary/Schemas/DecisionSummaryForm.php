<?php

namespace App\Filament\Resources\DecisionSummary\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DecisionSummaryForm
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
                Select::make('diocese_id')
                    ->relationship('diocese', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('level')
                    ->required()
                    ->maxLength(50),
                Textarea::make('summary_text')
                    ->columnSpanFull(),
                Toggle::make('is_successful'),
            ]);
    }
}