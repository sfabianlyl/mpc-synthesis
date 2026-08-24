<?php

namespace App\Filament\Resources\Extraction;

use App\Filament\Resources\Extraction\Pages\CreateExtraction;
use App\Filament\Resources\Extraction\Pages\EditExtraction;
use App\Filament\Resources\Extraction\Pages\ListExtractions;
use App\Filament\Resources\Extraction\Schemas\ExtractionForm;
use App\Filament\Resources\Extraction\Tables\ExtractionsTable;
use App\Models\Extraction;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExtractionResource extends Resource
{
    protected static ?string $model = Extraction::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return ExtractionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExtractionsTable::configure($table);
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
            'index' => ListExtractions::route('/'),
            'create' => CreateExtraction::route('/create'),
            'edit' => EditExtraction::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Extractions';
    }
}