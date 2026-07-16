<?php

namespace App\Filament\Resources\UploadedDocumentSource\Pages;

use App\Filament\Resources\UploadedDocumentSource\UploadedDocumentSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUploadedDocumentSources extends ListRecords
{
    protected static string $resource = UploadedDocumentSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
