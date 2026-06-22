<?php

namespace App\Filament\Resources\FinalDocuments\Pages;

use App\Filament\Resources\FinalDocuments\FinalDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinalDocuments extends ListRecords
{
    protected static string $resource = FinalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
