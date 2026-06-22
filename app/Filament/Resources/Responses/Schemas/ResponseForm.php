<?php

namespace App\Filament\Resources\Responses\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ResponseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('conversation_id')
                    ->relationship('conversation', 'title')
                    ->required(),
                Select::make('group_id')
                    ->relationship('group', 'name')
                    ->required(),
                Select::make('facilitator_id')
                    ->relationship('facilitator', 'name'),
                Textarea::make('consensus_text')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('submitted_at')
                    ->required(),
            ]);
    }
}
