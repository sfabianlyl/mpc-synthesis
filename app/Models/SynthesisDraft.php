<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string|null $convergence_text
 * @property string|null $consideration_text
 * @property string|null $proposals_text
 * @property string|null $other_text
 * @property bool $is_submitted
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $user
 */
class SynthesisDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'convergence_text',
        'consideration_text',
        'proposals_text',
        'other_text',
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
     * Get the conversation this draft belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who created this draft.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
