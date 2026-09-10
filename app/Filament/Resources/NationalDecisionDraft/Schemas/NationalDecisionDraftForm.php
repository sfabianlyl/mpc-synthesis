<?php

namespace App\Filament\Resources\NationalDecisionDraft\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NationalDecisionDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('conversation_id')
                    ->label('Conversation')
                    ->relationship('conversation', 'title')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->title . ($record->event ? " ({$record->event->title})" : ''))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('user_id')
                    ->label('Author')
                    ->relationship('user', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ($record->role ? ' (' . ucfirst($record->role) . ')' : ''))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('diocese_id')
                    ->label('Diocese')
                    ->relationship('diocese', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Toggle::make('is_submitted')
                    ->label('Is Submitted'),
                DateTimePicker::make('submitted_at')
                    ->label('Submitted At')
                    ->nullable(),
                Textarea::make('draft_text')
                    ->label('Draft Text')
                    ->rows(8)
                    ->columnSpanFull(),
            ]);
    }
}