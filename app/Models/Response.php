<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $group_id
 * @property string $consensus_text
 * @property Carbon $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read Group $group
 */
class Response extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_group_id', 'consensus_text', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    

    public function conversationGroup() 
    {
        return $this->belongsTo(ConversationGroup::class);
    }
}
