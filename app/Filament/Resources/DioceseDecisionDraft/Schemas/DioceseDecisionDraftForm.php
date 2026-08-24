<?php

namespace App\Filament\Resources\DioceseDecisionDraft\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DioceseDecisionDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->relationship('event', 'title')
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
                Textarea::make('draft_text')
                    ->columnSpanFull(),
                Toggle::make('is_submitted'),
            ]);
    }
}