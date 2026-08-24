<?php

namespace App\Filament\Resources\ConversationGroup\Pages;

use App\Filament\Resources\ConversationGroup\ConversationGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConversationGroup extends CreateRecord
{
    protected static string $resource = ConversationGroupResource::class;
}