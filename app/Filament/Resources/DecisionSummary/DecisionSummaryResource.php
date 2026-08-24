<?php

namespace App\Filament\Resources\DecisionSummary;

use App\Filament\Resources\DecisionSummary\Pages\CreateDecisionSummary;
use App\Filament\Resources\DecisionSummary\Pages\EditDecisionSummary;
use App\Filament\Resources\DecisionSummary\Pages\ListDecisionSummaries;
use App\Filament\Resources\DecisionSummary\Schemas\DecisionSummaryForm;
use App\Filament\Resources\DecisionSummary\Tables\DecisionSummariesTable;
use App\Models\DecisionSummary;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DecisionSummaryResource extends Resource
{
    protected static ?string $model = DecisionSummary::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return DecisionSummaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DecisionSummariesTable::configure($table);
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
            'index' => ListDecisionSummaries::route('/'),
            'create' => CreateDecisionSummary::route('/create'),
            'edit' => EditDecisionSummary::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Decision Summaries';
    }
}