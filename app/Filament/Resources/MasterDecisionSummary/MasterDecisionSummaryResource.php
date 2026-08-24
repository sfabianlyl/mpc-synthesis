<?php

namespace App\Filament\Resources\MasterDecisionSummary;

use App\Filament\Resources\MasterDecisionSummary\Pages\CreateMasterDecisionSummary;
use App\Filament\Resources\MasterDecisionSummary\Pages\EditMasterDecisionSummary;
use App\Filament\Resources\MasterDecisionSummary\Pages\ListMasterDecisionSummaries;
use App\Filament\Resources\MasterDecisionSummary\Schemas\MasterDecisionSummaryForm;
use App\Filament\Resources\MasterDecisionSummary\Tables\MasterDecisionSummariesTable;
use App\Models\MasterDecisionSummary;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MasterDecisionSummaryResource extends Resource
{
    protected static ?string $model = MasterDecisionSummary::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return MasterDecisionSummaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterDecisionSummariesTable::configure($table);
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
            'index' => ListMasterDecisionSummaries::route('/'),
            'create' => CreateMasterDecisionSummary::route('/create'),
            'edit' => EditMasterDecisionSummary::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Master Decision Summaries';
    }
}