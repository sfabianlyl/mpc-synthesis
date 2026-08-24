<?php

namespace App\Filament\Resources\SynthesisKeyPoint\Pages;

use App\Filament\Resources\SynthesisKeyPoint\SynthesisKeyPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSynthesisKeyPoints extends ListRecords
{
    protected static string $resource = SynthesisKeyPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}