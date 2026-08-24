<?php

namespace App\Filament\Resources\NationalDecisionDraft;

use App\Filament\Resources\NationalDecisionDraft\Pages\CreateNationalDecisionDraft;
use App\Filament\Resources\NationalDecisionDraft\Pages\EditNationalDecisionDraft;
use App\Filament\Resources\NationalDecisionDraft\Pages\ListNationalDecisionDrafts;
use App\Filament\Resources\NationalDecisionDraft\Schemas\NationalDecisionDraftForm;
use App\Filament\Resources\NationalDecisionDraft\Tables\NationalDecisionDraftsTable;
use App\Models\NationalDecisionDraft;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NationalDecisionDraftResource extends Resource
{
    protected static ?string $model = NationalDecisionDraft::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return NationalDecisionDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NationalDecisionDraftsTable::configure($table);
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
            'index' => ListNationalDecisionDrafts::route('/'),
            'create' => CreateNationalDecisionDraft::route('/create'),
            'edit' => EditNationalDecisionDraft::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'National Decision Drafts';
    }
}