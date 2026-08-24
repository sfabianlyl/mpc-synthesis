<?php

namespace App\Filament\Resources\Responses;

use App\Filament\Resources\Responses\Pages\CreateResponse;
use App\Filament\Resources\Responses\Pages\EditResponse;
use App\Filament\Resources\Responses\Pages\ListResponses;
use App\Filament\Resources\Responses\Schemas\ResponseForm;
use App\Filament\Resources\Responses\Tables\ResponsesTable;
use App\Models\Response;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResponseResource extends Resource
{
    protected static ?string $model = Response::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
