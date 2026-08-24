<?php

namespace App\Filament\Resources\SynthesisCustomQuery;

use App\Filament\Resources\SynthesisCustomQuery\Pages\CreateSynthesisCustomQuery;
use App\Filament\Resources\SynthesisCustomQuery\Pages\EditSynthesisCustomQuery;
use App\Filament\Resources\SynthesisCustomQuery\Pages\ListSynthesisCustomQueries;
use App\Filament\Resources\SynthesisCustomQuery\Schemas\SynthesisCustomQueryForm;
use App\Filament\Resources\SynthesisCustomQuery\Tables\SynthesisCustomQueriesTable;
use App\Models\SynthesisCustomQuery;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SynthesisCustomQueryResource extends Resource
{
    protected static ?string $model = SynthesisCustomQuery::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static UnitEnum|string|null $navigationGroup = 'Synthesis';

    public static function form(Schema $schema): Schema
    {
        return SynthesisCustomQueryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SynthesisCustomQueriesTable::configure($table);
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
            'index' => ListSynthesisCustomQueries::route('/'),
            'create' => CreateSynthesisCustomQuery::route('/create'),
            'edit' => EditSynthesisCustomQuery::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Synthesis Custom Queries';
    }
}