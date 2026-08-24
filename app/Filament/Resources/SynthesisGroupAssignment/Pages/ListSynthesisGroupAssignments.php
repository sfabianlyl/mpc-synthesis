<?php

namespace App\Filament\Resources\SynthesisGroupAssignment\Pages;

use App\Filament\Resources\SynthesisGroupAssignment\SynthesisGroupAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSynthesisGroupAssignments extends ListRecords
{
    protected static string $resource = SynthesisGroupAssignmentResource::class;

    public function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}