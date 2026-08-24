<?php

namespace App\Filament\Resources\NationalDecisionDraft\Pages;

use App\Filament\Resources\NationalDecisionDraft\NationalDecisionDraftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNationalDecisionDraft extends EditRecord
{
    protected static string $resource = NationalDecisionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}