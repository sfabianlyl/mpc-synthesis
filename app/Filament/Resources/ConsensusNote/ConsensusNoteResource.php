<?php

namespace App\Filament\Resources\ConsensusNote;

use App\Filament\Resources\ConsensusNote\Pages\CreateConsensusNote;
use App\Filament\Resources\ConsensusNote\Pages\EditConsensusNote;
use App\Filament\Resources\ConsensusNote\Pages\ListConsensusNotes;
use App\Filament\Resources\ConsensusNote\Schemas\ConsensusNoteForm;
use App\Filament\Resources\ConsensusNote\Tables\ConsensusNotesTable;
use App\Models\ConsensusNote;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConsensusNoteResource extends Resource
{
    protected static ?string $model = ConsensusNote::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return ConsensusNoteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsensusNotesTable::configure($table);
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
            'index' => ListConsensusNotes::route('/'),
            'create' => CreateConsensusNote::route('/create'),
            'edit' => EditConsensusNote::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Consensus Notes';
    }
}