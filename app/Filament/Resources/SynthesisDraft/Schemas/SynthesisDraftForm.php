<?php

namespace App\Filament\Resources\SynthesisDraft\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SynthesisDraftForm
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
                    ->required(),
                Textarea::make('convergence_text')
                    ->columnSpanFull(),
                Textarea::make('consideration_text')
                    ->columnSpanFull(),
                Textarea::make('proposals_text')
                    ->columnSpanFull(),
                Textarea::make('other_text')
                    ->columnSpanFull(),
            ]);
    }
}