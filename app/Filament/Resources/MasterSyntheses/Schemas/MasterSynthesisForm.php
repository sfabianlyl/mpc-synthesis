<?php

namespace App\Filament\Resources\MasterSyntheses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MasterSynthesisForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('conversation_id')
                    ->relationship('conversation', 'title')
                    ->required(),
                Select::make('synthesis_id')
                    ->relationship('synthesis', 'name')
                    ->required(),
                Textarea::make('convergence_text')
                    ->columnSpanFull(),
                Textarea::make('consideration_text')
                    ->columnSpanFull(),
                Textarea::make('proposals_text')
                    ->columnSpanFull(),
                Toggle::make('llm_generated')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
            ]);
    }
}
