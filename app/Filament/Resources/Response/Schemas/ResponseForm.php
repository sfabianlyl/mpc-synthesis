<?php

namespace App\Filament\Resources\Response\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ResponseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('conversation_group_id')
                    ->relationship('conversationGroup', 'id')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('consensus_text')
                    ->columnSpanFull(),
                TextInput::make('submitted_at')
                    ->dateTime()
                    ->nullable(),
            ]);
    }
}