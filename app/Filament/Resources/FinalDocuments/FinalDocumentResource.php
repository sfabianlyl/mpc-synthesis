<?php

namespace App\Filament\Resources\FinalDocuments;

use App\Filament\Resources\FinalDocuments\Pages\CreateFinalDocument;
use App\Filament\Resources\FinalDocuments\Pages\EditFinalDocument;
use App\Filament\Resources\FinalDocuments\Pages\ListFinalDocuments;
use App\Filament\Resources\FinalDocuments\Schemas\FinalDocumentForm;
use App\Filament\Resources\FinalDocuments\Tables\FinalDocumentsTable;
use App\Models\FinalDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FinalDocumentResource extends Resource
{
    protected static ?string $model = FinalDocument::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FinalDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinalDocumentsTable::configure($table);
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
            'index' => ListFinalDocuments::route('/'),
            'create' => CreateFinalDocument::route('/create'),
            'edit' => EditFinalDocument::route('/{record}/edit'),
        ];
    }
}
