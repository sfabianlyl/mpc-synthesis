<?php

namespace App\Filament\Resources\ConsensusNote\Pages;

use App\Filament\Resources\ConsensusNote\ConsensusNoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConsensusNotes extends ListRecords
{
    protected static string $resource = ConsensusNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}