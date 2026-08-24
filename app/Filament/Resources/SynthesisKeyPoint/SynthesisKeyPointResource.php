<?php

namespace App\Filament\Resources\SynthesisKeyPoint;

use App\Filament\Resources\SynthesisKeyPoint\Pages\CreateSynthesisKeyPoint;
use App\Filament\Resources\SynthesisKeyPoint\Pages\EditSynthesisKeyPoint;
use App\Filament\Resources\SynthesisKeyPoint\Pages\ListSynthesisKeyPoints;
use App\Filament\Resources\SynthesisKeyPoint\Schemas\SynthesisKeyPointForm;
use App\Filament\Resources\SynthesisKeyPoint\Tables\SynthesisKeyPointsTable;
use App\Models\SynthesisKeyPoint;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SynthesisKeyPointResource extends Resource
{
    protected static ?string $model = SynthesisKeyPoint::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedStar;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return SynthesisKeyPointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SynthesisKeyPointsTable::configure($table);
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
            'index' => ListSynthesisKeyPoints::route('/'),
            'create' => CreateSynthesisKeyPoint::route('/create'),
            'edit' => EditSynthesisKeyPoint::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Synthesis Key Points';
    }
}