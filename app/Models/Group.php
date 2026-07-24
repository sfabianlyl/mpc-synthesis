<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $synthesis_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $synthesis
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Response> $responses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MasterSynthesis> $masterSynthesis
 */
class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'synthesis_id'];

    /**
     * Get the synthesis team member assigned to this group.
     *
     * @deprecated Synthesis is now assigned at conversation level via conversation_members pivot table.
     * Use User::assignedConversations() instead.
     */
    public function synthesis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synthesis_id');
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
