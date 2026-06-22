<?php

namespace App\Filament\Resources\SynthesisReports\Pages;

use App\Filament\Resources\SynthesisReports\SynthesisReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSynthesisReports extends ListRecords
{
    protected static string $resource = SynthesisReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
