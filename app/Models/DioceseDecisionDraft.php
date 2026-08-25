<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int|null $diocese_id
 * @property string|null $draft_text
 * @property bool $is_submitted
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $user
 * @property-read Diocese|null $diocese
 */
class DioceseDecisionDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'diocese_id',
        'draft_text',
        'is_submitted',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_submitted' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Get the conversation this diocese decision draft belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who created this diocese decision draft.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the diocese associated with this diocese decision draft.
     */
    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }
}

