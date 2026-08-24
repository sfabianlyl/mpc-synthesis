<?php

namespace App\Filament\Resources\MasterDecisionSummary\Pages;

use App\Filament\Resources\MasterDecisionSummary\MasterDecisionSummaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterDecisionSummaries extends ListRecords
{
    protected static string $resource = MasterDecisionSummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}