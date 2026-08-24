<?php

namespace App\Filament\Resources\SynthesisKeyPoint\Pages;

use App\Filament\Resources\SynthesisKeyPoint\SynthesisKeyPointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSynthesisKeyPoint extends EditRecord
{
    protected static string $resource = SynthesisKeyPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}