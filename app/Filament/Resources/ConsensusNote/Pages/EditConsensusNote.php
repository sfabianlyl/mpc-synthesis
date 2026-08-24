<?php

namespace App\Filament\Resources\ConsensusNote\Pages;

use App\Filament\Resources\ConsensusNote\ConsensusNoteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConsensusNote extends EditRecord
{
    protected static string $resource = ConsensusNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}