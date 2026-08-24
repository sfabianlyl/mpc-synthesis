<?php

namespace App\Filament\Resources\Response;

use App\Filament\Resources\Response\Pages\CreateResponse;
use App\Filament\Resources\Response\Pages\EditResponse;
use App\Filament\Resources\Response\Pages\ListResponses;
use App\Filament\Resources\Response\Schemas\ResponseForm;
use App\Filament\Resources\Response\Tables\ResponsesTable;
use App\Models\Response;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResponseResource extends Resource
{
    protected static ?string $model = Response::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return ResponseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResponsesTable::configure($table);
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
            'index' => ListResponses::route('/'),
            'create' => CreateResponse::route('/create'),
            'edit' => EditResponse::route('/{record}/edit'),
        ];
    }
}