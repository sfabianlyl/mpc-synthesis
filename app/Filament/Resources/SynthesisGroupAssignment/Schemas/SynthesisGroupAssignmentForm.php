<?php

namespace App\Filament\Resources\SynthesisGroupAssignment\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class SynthesisGroupAssignmentForm
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
                    ->required(),
                Select::make('group_id')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}