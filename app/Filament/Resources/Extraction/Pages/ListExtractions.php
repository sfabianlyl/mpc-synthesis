<?php

namespace App\Filament\Resources\Extraction\Pages;

use App\Filament\Resources\Extraction\ExtractionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtractions extends ListRecords
{
    protected static string $resource = ExtractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}