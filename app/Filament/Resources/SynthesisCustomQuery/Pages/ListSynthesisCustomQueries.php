<?php

namespace App\Filament\Resources\SynthesisCustomQuery\Pages;

use App\Filament\Resources\SynthesisCustomQuery\SynthesisCustomQueryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ListRecords;

class ListSynthesisCustomQueries extends ListRecords
{
    protected static string $resource = SynthesisCustomQueryResource::class;

    public function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}