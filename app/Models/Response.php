<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $conversation_group_id
 * @property string|null $consensus_text
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ConversationGroup|null $conversationGroup
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
