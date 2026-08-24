<?php

namespace App\Filament\Resources\SynthesisDraft\Pages;

use App\Filament\Resources\SynthesisDraft\SynthesisDraftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSynthesisDraft extends EditRecord
{
    protected static string $resource = SynthesisDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}