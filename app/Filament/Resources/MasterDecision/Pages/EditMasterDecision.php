<?php

namespace App\Filament\Resources\MasterDecision\Pages;

use App\Filament\Resources\MasterDecision\MasterDecisionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterDecision extends EditRecord
{
    protected static string $resource = MasterDecisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}