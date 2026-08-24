<?php

namespace App\Filament\Resources\MasterDecisionSummary\Pages;

use App\Filament\Resources\MasterDecisionSummary\MasterDecisionSummaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterDecisionSummary extends EditRecord
{
    protected static string $resource = MasterDecisionSummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}