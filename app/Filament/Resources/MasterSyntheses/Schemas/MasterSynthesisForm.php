<?php

namespace App\Filament\Resources\MasterSyntheses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MasterSynthesisForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('conversation_id')
                    ->required(),
                Textarea::make('convergence_text')
                    ->columnSpanFull(),
                Textarea::make('consideration_text')
                    ->columnSpanFull(),
                Textarea::make('proposals_text')
                    ->columnSpanFull(),
                Textarea::make('other_text')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
            ]);
    }
}
