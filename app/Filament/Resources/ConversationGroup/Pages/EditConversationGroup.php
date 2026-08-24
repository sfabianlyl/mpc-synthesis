<?php

namespace App\Filament\Resources\ConversationGroup\Pages;

use App\Filament\Resources\ConversationGroup\ConversationGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConversationGroup extends EditRecord
{
    protected static string $resource = ConversationGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}