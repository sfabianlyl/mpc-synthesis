<?php

namespace App\Filament\Resources\SynthesisSummary\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SynthesisSummaryForm
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
                Textarea::make('convergence_summary')
                    ->label('Convergence Summary')
                    ->columnSpanFull()
                    ->rows(4),
                Textarea::make('consideration_summary')
                    ->label('Consideration Summary')
                    ->columnSpanFull()
                    ->rows(4),
                Textarea::make('proposals_summary')
                    ->label('Proposals Summary')
                    ->columnSpanFull()
                    ->rows(4),
                Textarea::make('other_summary')
                    ->label('Other Summary')
                    ->columnSpanFull()
                    ->rows(4),
                Toggle::make('is_successful'),
            ]);
    }
}