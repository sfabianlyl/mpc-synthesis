<?php

namespace App\Filament\Resources\UploadedDocumentSource;

use App\Filament\Resources\UploadedDocumentSource\Pages\CreateUploadedDocumentSource;
use App\Filament\Resources\UploadedDocumentSource\Pages\EditUploadedDocumentSource;
use App\Filament\Resources\UploadedDocumentSource\Pages\ListUploadedDocumentSources;
use App\Filament\Resources\UploadedDocumentSource\Schemas\UploadedDocumentSourceForm;
use App\Filament\Resources\UploadedDocumentSource\Tables\UploadedDocumentSourcesTable;
use App\Models\UploadedDocumentSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UploadedDocumentSourceResource extends Resource
{
    protected static ?string $model = UploadedDocumentSource::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    public static function form(Schema $schema): Schema
    {
        return UploadedDocumentSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UploadedDocumentSourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\UploadedDocumentSource\RelationManagers\DocumentSourceChunks::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUploadedDocumentSources::route('/'),
            'create' => CreateUploadedDocumentSource::route('/create'),
            'edit' => EditUploadedDocumentSource::route('/{record}/edit'),
        ];
    }
}
