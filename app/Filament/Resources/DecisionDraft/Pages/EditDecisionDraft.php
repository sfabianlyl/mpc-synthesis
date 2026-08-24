<?php

namespace App\Filament\Resources\DecisionDraft\Pages;

use App\Filament\Resources\DecisionDraft\DecisionDraftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDecisionDraft extends EditRecord
{
    protected static string $resource = DecisionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}