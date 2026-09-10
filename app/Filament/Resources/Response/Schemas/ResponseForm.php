<?php

namespace App\Filament\Resources\Response\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ResponseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('conversation_group_id')
                    ->label('Conversation Group')
                    ->relationship('conversationGroup', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "#{$record->id} - " . ($record->group?->name ?? 'Group') . " (" . ($record->conversation?->title ?? 'Conversation') . ")")
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('consensus_text')
                    ->columnSpanFull(),
                DateTimePicker::make('submitted_at')
                    ->nullable(),
            ]);
    }
}