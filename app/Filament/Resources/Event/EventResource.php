<?php

namespace App\Filament\Resources\Event;

use App\Filament\Resources\Event\Pages\CreateEvent;
use App\Filament\Resources\Event\Pages\EditEvent;
use App\Filament\Resources\Event\Pages\ListEvents;
use App\Filament\Resources\Event\Schemas\EventForm;
use App\Filament\Resources\Event\Tables\EventsTable;
use App\Models\Event;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Event\RelationManagers\NationalDecisionDrafts::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}