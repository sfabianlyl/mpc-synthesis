<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int $response_id
 * @property string|null $text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $user
 * @property-read Response $response
 */
class ConsensusNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'response_id',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'text' => 'string',
        ];
    }

    /**
     * Get the conversation this note belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who wrote this note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the response this note is for.
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }
}
