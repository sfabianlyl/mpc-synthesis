<?php

namespace App\Filament\Resources\ConversationGroup\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConversationGroupForm
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
                Select::make('group_id')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('consensus_text')
                    ->columnSpanFull(),
            ]);
    }
}