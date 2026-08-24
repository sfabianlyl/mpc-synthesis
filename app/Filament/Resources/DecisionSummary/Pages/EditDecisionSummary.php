<?php

namespace App\Filament\Resources\DecisionSummary\Pages;

use App\Filament\Resources\DecisionSummary\DecisionSummaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDecisionSummary extends EditRecord
{
    protected static string $resource = DecisionSummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}