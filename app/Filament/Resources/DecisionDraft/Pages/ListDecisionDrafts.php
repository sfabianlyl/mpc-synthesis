<?php

namespace App\Filament\Resources\DecisionDraft\Pages;

use App\Filament\Resources\DecisionDraft\DecisionDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDecisionDrafts extends ListRecords
{
    protected static string $resource = DecisionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}