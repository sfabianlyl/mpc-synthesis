<?php

namespace App\Filament\Resources\FinalDocuments\Pages;

use App\Filament\Resources\FinalDocuments\FinalDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinalDocument extends EditRecord
{
    protected static string $resource = FinalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
