<?php

namespace App\Filament\Resources\SynthesisDraft;

use App\Filament\Resources\SynthesisDraft\Pages\CreateSynthesisDraft;
use App\Filament\Resources\SynthesisDraft\Pages\EditSynthesisDraft;
use App\Filament\Resources\SynthesisDraft\Pages\ListSynthesisDrafts;
use App\Filament\Resources\SynthesisDraft\Schemas\SynthesisDraftForm;
use App\Filament\Resources\SynthesisDraft\Tables\SynthesisDraftsTable;
use App\Models\SynthesisDraft;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SynthesisDraftResource extends Resource
{
    protected static ?string $model = SynthesisDraft::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static UnitEnum|string|null $navigationGroup = 'Core Data';

    public static function form(Schema $schema): Schema
    {
        return SynthesisDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SynthesisDraftsTable::configure($table);
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
            'index' => ListSynthesisDrafts::route('/'),
            'create' => CreateSynthesisDraft::route('/create'),
            'edit' => EditSynthesisDraft::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Synthesis Drafts';
    }
}