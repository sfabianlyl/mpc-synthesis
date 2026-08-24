<?php

namespace App\Filament\Resources\MasterDecision\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MasterDecisionForm
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
                Select::make('submitted_by_user_id')
                    ->relationship('submittedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('master_text')
                    ->columnSpanFull(),
                Toggle::make('is_submitted'),
            ]);
    }
}