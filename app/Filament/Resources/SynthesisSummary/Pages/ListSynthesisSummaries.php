<?php

namespace App\Filament\Resources\SynthesisSummary\Pages;

use App\Filament\Resources\SynthesisSummary\SynthesisSummaryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ListRecords;

class ListSynthesisSummaries extends ListRecords
{
    protected static string $resource = SynthesisSummaryResource::class;

    public function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}

class CreateSynthesisSummary extends CreateRecord
{
    protected static string $resource = SynthesisSummaryResource::class;
}

class EditSynthesisSummary extends \Filament\Resources\Pages\EditRecord
{
    protected static string $resource = SynthesisSummaryResource::class;
}