<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string $name
 * @property int|null $synthesis_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read User|null $synthesis
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Response> $responses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MasterSynthesis> $masterSynthesis
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Conversation> $conversations
 */
class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'synthesis_id', 'event_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the synthesis team member assigned to this group.
     */
    public function synthesis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synthesis_id');
    }

    /**
     * Get the conversations this group participates in.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_group');
    }

    /**
     * Get the responses submitted for this group.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * Get the synthesis reports for this group.
     */
    public function masterSynthesis(): HasMany
    {
        return $this->hasMany(MasterSynthesis::class);
    }
}
