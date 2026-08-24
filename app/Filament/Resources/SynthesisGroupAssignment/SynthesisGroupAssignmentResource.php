<?php

namespace App\Filament\Resources\SynthesisGroupAssignment;

use App\Filament\Resources\SynthesisGroupAssignment\Pages\CreateSynthesisGroupAssignment;
use App\Filament\Resources\SynthesisGroupAssignment\Pages\EditSynthesisGroupAssignment;
use App\Filament\Resources\SynthesisGroupAssignment\Pages\ListSynthesisGroupAssignments;
use App\Filament\Resources\SynthesisGroupAssignment\Schemas\SynthesisGroupAssignmentForm;
use App\Filament\Resources\SynthesisGroupAssignment\Tables\SynthesisGroupAssignmentsTable;
use App\Models\SynthesisGroupAssignment;
use Filament\Resources\Resource;
use UnitEnum;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SynthesisGroupAssignmentResource extends Resource
{
    protected static ?string $model = SynthesisGroupAssignment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static UnitEnum|string|null $navigationGroup = 'Synthesis';

    public static function form(Schema $schema): Schema
    {
        return SynthesisGroupAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SynthesisGroupAssignmentsTable::configure($table);
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
            'index' => ListSynthesisGroupAssignments::route('/'),
            'create' => CreateSynthesisGroupAssignment::route('/create'),
            'edit' => EditSynthesisGroupAssignment::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return 'Synthesis Group Assignments';
    }
}