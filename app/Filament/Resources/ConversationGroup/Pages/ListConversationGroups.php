<?php

namespace App\Filament\Resources\ConversationGroup\Pages;

use App\Filament\Resources\ConversationGroup\ConversationGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConversationGroups extends ListRecords
{
    protected static string $resource = ConversationGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}