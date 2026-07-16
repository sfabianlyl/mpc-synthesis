<?php

namespace App\Filament\Resources\UploadedDocumentSource\Pages;

use App\Filament\Resources\UploadedDocumentSource\UploadedDocumentSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUploadedDocumentSource extends EditRecord
{
    protected static string $resource = UploadedDocumentSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
