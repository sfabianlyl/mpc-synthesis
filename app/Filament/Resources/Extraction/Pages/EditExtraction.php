<?php

namespace App\Filament\Resources\Extraction\Pages;

use App\Filament\Resources\Extraction\ExtractionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtraction extends EditRecord
{
    protected static string $resource = ExtractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}