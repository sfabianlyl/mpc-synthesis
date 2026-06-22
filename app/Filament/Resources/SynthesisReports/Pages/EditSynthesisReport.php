<?php

namespace App\Filament\Resources\SynthesisReports\Pages;

use App\Filament\Resources\SynthesisReports\SynthesisReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSynthesisReport extends EditRecord
{
    protected static string $resource = SynthesisReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
