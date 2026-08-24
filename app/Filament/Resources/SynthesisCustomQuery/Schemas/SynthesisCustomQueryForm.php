<?php

namespace App\Filament\Resources\SynthesisCustomQuery\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SynthesisCustomQueryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('synthesis_draft_id')
                    ->relationship('synthesisDraft', 'id')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('prompt')
                    ->columnSpanFull()
                    ->rows(4)
                    ->required(),
                TextInput::make('provider')
                    ->label('Provider')
                    ->placeholder('e.g., openai'),
            ]);
    }
}