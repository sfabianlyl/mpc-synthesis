<?php

namespace App\Filament\Resources\Diocese\Pages;

use App\Filament\Resources\Diocese\DioceseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDioceses extends ListRecords
{
    protected static string $resource = DioceseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}