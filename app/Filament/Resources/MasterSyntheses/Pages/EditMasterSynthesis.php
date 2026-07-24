<?php

namespace App\Filament\Resources\MasterSyntheses\Pages;

use App\Filament\Resources\MasterSyntheses\MasterSynthesisResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterSynthesis extends EditRecord
{
    protected static string $resource = MasterSynthesisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
