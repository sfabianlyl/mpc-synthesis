<?php

namespace App\Filament\Resources\MasterSyntheses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->searchable()
                    ->preload()
                    ->required(),
                Toggle::make('is_master_synthesis'),
                Textarea::make('convergence_text')
                    ->columnSpanFull(),
                Textarea::make('consideration_text')
                    ->columnSpanFull(),
                Textarea::make('proposals_text')
                    ->columnSpanFull(),
                Textarea::make('other_text')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
            ]);
    }
}
