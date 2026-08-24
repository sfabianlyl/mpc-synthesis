<?php

namespace App\Filament\Resources\MasterDecision\Pages;

use App\Filament\Resources\MasterDecision\MasterDecisionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterDecisions extends ListRecords
{
    protected static string $resource = MasterDecisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}