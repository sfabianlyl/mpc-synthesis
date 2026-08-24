<?php

namespace App\Filament\Resources\MasterDecision;

use App\Filament\Resources\MasterDecision\Pages\CreateMasterDecision;
use App\Filament\Resources\MasterDecision\Pages\EditMasterDecision;
use App\Filament\Resources\MasterDecision\Pages\ListMasterDecisions;
use App\Filament\Resources\MasterDecision\Schemas\MasterDecisionForm;
use App\Filament\Resources\MasterDecision\Tables\MasterDecisionsTable;
use App\Models\MasterDecision;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MasterDecisionResource extends Resource
{
    protected static ?string $model = MasterDecision::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return MasterDecisionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterDecisionsTable::configure($table);
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
            'index' => ListMasterDecisions::route('/'),
            'create' => CreateMasterDecision::route('/create'),
            'edit' => EditMasterDecision::route('/{record}/edit'),
        ];
    }
}