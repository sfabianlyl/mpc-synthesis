<?php

namespace App\Filament\Resources\DioceseDecisionDraft\Pages;

use App\Filament\Resources\DioceseDecisionDraft\DioceseDecisionDraftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDioceseDecisionDraft extends EditRecord
{
    protected static string $resource = DioceseDecisionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}