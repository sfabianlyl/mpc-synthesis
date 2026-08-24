<?php

namespace App\Filament\Resources\NationalDecisionDraft\Pages;

use App\Filament\Resources\NationalDecisionDraft\NationalDecisionDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNationalDecisionDrafts extends ListRecords
{
    protected static string $resource = NationalDecisionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}