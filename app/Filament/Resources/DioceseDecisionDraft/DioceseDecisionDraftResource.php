<?php

namespace App\Filament\Resources\DioceseDecisionDraft;

use App\Filament\Resources\DioceseDecisionDraft\Pages\CreateDioceseDecisionDraft;
use App\Filament\Resources\DioceseDecisionDraft\Pages\EditDioceseDecisionDraft;
use App\Filament\Resources\DioceseDecisionDraft\Pages\ListDioceseDecisionDrafts;
use App\Filament\Resources\DioceseDecisionDraft\Schemas\DioceseDecisionDraftForm;
use App\Filament\Resources\DioceseDecisionDraft\Tables\DioceseDecisionDraftsTable;
use App\Models\DioceseDecisionDraft;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DioceseDecisionDraftResource extends Resource
{
    protected static ?string $model = DioceseDecisionDraft::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return DioceseDecisionDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DioceseDecisionDraftsTable::configure($table);
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
            'index' => ListDioceseDecisionDrafts::route('/'),
            'create' => CreateDioceseDecisionDraft::route('/create'),
            'edit' => EditDioceseDecisionDraft::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Diocese Decision Drafts';
    }
}