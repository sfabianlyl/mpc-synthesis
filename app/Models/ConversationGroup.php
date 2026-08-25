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
class ConversationGroup extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'group_id', 'submitted_at'];

    public function conversation() 
    {
        return $this->belongsTo(Conversation::class);
    }

    // The specific group
    public function group() 
    {
        return $this->belongsTo(Group::class);
    }

    // Responses now belong to this specific intersection
    public function responses() 
    {
        return $this->hasMany(Response::class);
    }

    // Synthesis members assigned to this specific group WITHIN this conversation
    public function synthesisMembers() 
    {
        // Assumes your users table is used for synthesis members.
        // Pivot table: conversation_group_user (conversation_group_id, user_id)
        return $this->belongsToMany(User::class, 'conversation_group_user')
            ->withPivot('is_synthesizer')
            ->withTimestamps();
    }
}
