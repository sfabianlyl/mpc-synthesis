<?php

namespace App\Filament\Resources\SynthesisSummary;

use App\Filament\Resources\SynthesisSummary\Pages\CreateSynthesisSummary;
use App\Filament\Resources\SynthesisSummary\Pages\EditSynthesisSummary;
use App\Filament\Resources\SynthesisSummary\Pages\ListSynthesisSummaries;
use App\Filament\Resources\SynthesisSummary\Schemas\SynthesisSummaryForm;
use App\Filament\Resources\SynthesisSummary\Tables\SynthesisSummariesTable;
use App\Models\SynthesisSummary;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SynthesisSummaryResource extends Resource
{
    protected static ?string $model = SynthesisSummary::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static UnitEnum|string|null $navigationGroup = 'Synthesis';

    public static function form(Schema $schema): Schema
    {
        return SynthesisSummaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SynthesisSummariesTable::configure($table);
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
            'index' => ListSynthesisSummaries::route('/'),
            'create' => CreateSynthesisSummary::route('/create'),
            'edit' => EditSynthesisSummary::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Synthesis Summaries';
    }
}