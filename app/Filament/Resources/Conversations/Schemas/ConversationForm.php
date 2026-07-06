<?php

namespace App\Filament\Resources\Conversations\Schemas;

use App\Models\Conversation;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ConversationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->required()
                    ->default('active')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'archived' => 'Archived',
                    ]),
                CheckboxList::make('synthesis_members')
                    ->label('Synthesis Members')
                    ->options(User::where('role', 'synthesis')->pluck('name', 'id'))
                    ->searchable()
                    ->bulkToggleable()
                    ->required(),
            ])
            ->mutateFormDataBeforeSave(function (array $data): array {
                if (isset($data['synthesis_members'])) {
                    $data['_synthesis_members_sync'] = true;
                }
                return $data;
            })
            ->afterSaved(function (Conversation $record, array $data) {
                if (isset($data['_synthesis_members_sync'])) {
                    $record->synthesisMembers()->sync($data['synthesis_members'] ?? []);
                }
            });
    }
}
