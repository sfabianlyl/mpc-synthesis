<?php

namespace App\Filament\Resources\SynthesisReports;

use App\Filament\Resources\SynthesisReports\Pages\CreateSynthesisReport;
use App\Filament\Resources\SynthesisReports\Pages\EditSynthesisReport;
use App\Filament\Resources\SynthesisReports\Pages\ListSynthesisReports;
use App\Filament\Resources\SynthesisReports\Schemas\SynthesisReportForm;
use App\Filament\Resources\SynthesisReports\Tables\SynthesisReportsTable;
use App\Models\SynthesisReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SynthesisReportResource extends Resource
{
    protected static ?string $model = SynthesisReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SynthesisReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SynthesisReportsTable::configure($table);
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
            'index' => ListSynthesisReports::route('/'),
            'create' => CreateSynthesisReport::route('/create'),
            'edit' => EditSynthesisReport::route('/{record}/edit'),
        ];
    }
}
