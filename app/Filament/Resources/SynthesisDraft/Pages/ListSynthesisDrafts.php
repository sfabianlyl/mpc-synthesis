<?php

namespace App\Filament\Resources\SynthesisDraft\Pages;

use App\Filament\Resources\SynthesisDraft\SynthesisDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSynthesisDrafts extends ListRecords
{
    protected static string $resource = SynthesisDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}