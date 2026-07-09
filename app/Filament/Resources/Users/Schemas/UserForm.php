<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->required()
                    ->default('admin')
                    ->options([
                        'admin' => 'Admin',
                        'drafter' => 'Drafter',
                        'synthesis' => 'Synthesis',
                    ]),
                TextInput::make('password')
                    ->password()
                    ->dehydrateAction(fn ($set) => $set('password', bcrypt($set('password'))))
                    ->dehydrated(fn ($state) => filled($state))
                    ->minLength(8),
            ]);
    }
}
