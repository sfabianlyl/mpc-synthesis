<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int $group_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $user
 * @property-read Group $group
 */
class SynthesisGroupAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'group_id',
    ];

    /**
     * Get the conversation this assignment belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user this assignment is for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group assigned.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
