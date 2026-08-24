<?php

namespace App\Filament\Resources\MasterDecisionSummary\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MasterDecisionSummaryForm
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
                Textarea::make('master_text')
                    ->columnSpanFull(),
                Toggle::make('is_editable'),
            ]);
    }
}