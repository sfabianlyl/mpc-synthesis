<?php

namespace App\Filament\Resources\ConsensusNote\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ConsensusNoteForm
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
                Select::make('response_id')
                    ->relationship('response', 'id')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Textarea::make('text')
                    ->columnSpanFull(),
            ]);
    }
}