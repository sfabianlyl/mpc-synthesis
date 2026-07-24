<?php

namespace App\Filament\Resources\MasterSyntheses;

use App\Filament\Resources\MasterSyntheses\Pages\CreateMasterSynthesis;
use App\Filament\Resources\MasterSyntheses\Pages\EditMasterSynthesis;
use App\Filament\Resources\MasterSyntheses\Pages\ListMasterSyntheses;
use App\Filament\Resources\MasterSyntheses\Schemas\MasterSynthesisForm;
use App\Filament\Resources\MasterSyntheses\Tables\MasterSynthesesTable;
use App\Models\MasterSynthesis;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MasterSynthesisResource extends Resource
{
    protected static ?string $model = MasterSynthesis::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MasterSynthesisForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterSynthesesTable::configure($table);
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
            'index' => ListMasterSyntheses::route('/'),
            'create' => CreateMasterSynthesis::route('/create'),
            'edit' => EditMasterSynthesis::route('/{record}/edit'),
        ];
    }
}
