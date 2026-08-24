<?php

namespace App\Filament\Resources\DioceseDecisionDraft\Pages;

use App\Filament\Resources\DioceseDecisionDraft\DioceseDecisionDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDioceseDecisionDrafts extends ListRecords
{
    protected static string $resource = DioceseDecisionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}