<?php

namespace App\Filament\Resources\Diocese;

use App\Filament\Resources\Diocese\Pages\CreateDiocese;
use App\Filament\Resources\Diocese\Pages\EditDiocese;
use App\Filament\Resources\Diocese\Pages\ListDioceses;
use App\Filament\Resources\Diocese\Schemas\DioceseForm;
use App\Filament\Resources\Diocese\Tables\DiocesesTable;
use App\Models\Diocese;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DioceseResource extends Resource
{
    protected static ?string $model = Diocese::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static UnitEnum|string|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return DioceseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiocesesTable::configure($table);
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
            'index' => ListDioceses::route('/'),
            'create' => CreateDiocese::route('/create'),
            'edit' => EditDiocese::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Dioceses';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('users');
    }
}