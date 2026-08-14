<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Response> $responses
 */
class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'event_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the responses submitted for this group.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function conversationGroups() 
    {
        return $this->hasMany(ConversationGroup::class);
    }
}
