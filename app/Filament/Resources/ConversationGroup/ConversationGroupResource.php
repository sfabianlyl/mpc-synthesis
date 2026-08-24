<?php

namespace App\Filament\Resources\ConversationGroup;

use App\Filament\Resources\ConversationGroup\Pages\CreateConversationGroup;
use App\Filament\Resources\ConversationGroup\Pages\EditConversationGroup;
use App\Filament\Resources\ConversationGroup\Pages\ListConversationGroups;
use App\Filament\Resources\ConversationGroup\Schemas\ConversationGroupForm;
use App\Filament\Resources\ConversationGroup\Tables\ConversationGroupsTable;
use App\Models\ConversationGroup;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConversationGroupResource extends Resource
{
    protected static ?string $model = ConversationGroup::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return ConversationGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversationGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversationGroups::route('/'),
            'create' => CreateConversationGroup::route('/create'),
            'edit' => EditConversationGroup::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Conversation Groups';
    }
}