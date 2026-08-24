<?php

namespace App\Filament\Resources\DecisionDraft;

use App\Filament\Resources\DecisionDraft\Pages\CreateDecisionDraft;
use App\Filament\Resources\DecisionDraft\Pages\EditDecisionDraft;
use App\Filament\Resources\DecisionDraft\Pages\ListDecisionDrafts;
use App\Filament\Resources\DecisionDraft\Schemas\DecisionDraftForm;
use App\Filament\Resources\DecisionDraft\Tables\DecisionDraftsTable;
use App\Models\DecisionDraft;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DecisionDraftResource extends Resource
{
    protected static ?string $model = DecisionDraft::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return DecisionDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DecisionDraftsTable::configure($table);
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
            'index' => ListDecisionDrafts::route('/'),
            'create' => CreateDecisionDraft::route('/create'),
            'edit' => EditDecisionDraft::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Decision Drafts';
    }
}