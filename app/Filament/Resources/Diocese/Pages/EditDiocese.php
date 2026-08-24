<?php

namespace App\Filament\Resources\Diocese\Pages;

use App\Filament\Resources\Diocese\DioceseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiocese extends EditRecord
{
    protected static string $resource = DioceseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}