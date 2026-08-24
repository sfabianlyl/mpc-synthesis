<?php

namespace App\Filament\Resources\DecisionDraft\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DecisionDraftForm
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
                    ->nullable(),
                Select::make('diocese_id')
                    ->relationship('diocese', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Textarea::make('national_text')
                    ->columnSpanFull(),
                Textarea::make('diocesan_text')
                    ->columnSpanFull(),
                Toggle::make('is_submitted'),
            ]);
    }
}