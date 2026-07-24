<?php

namespace App\Filament\Resources\MasterSyntheses\Pages;

use App\Filament\Resources\MasterSyntheses\MasterSynthesisResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterSyntheses extends ListRecords
{
    protected static string $resource = MasterSynthesisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
