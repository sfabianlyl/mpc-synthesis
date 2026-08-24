<?php

namespace App\Filament\Resources\Diocese\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DioceseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->columnSpanFull(),
            ]);
    }
}