<?php

namespace App\Filament\Resources\DecisionSummary\Pages;

use App\Filament\Resources\DecisionSummary\DecisionSummaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDecisionSummaries extends ListRecords
{
    protected static string $resource = DecisionSummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}