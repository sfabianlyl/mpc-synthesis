<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $location
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Conversation> $conversations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, NationalDecisionDraft> $nationalDecisionDrafts
 * @property-read MasterDecisionSummary|null $masterDecisionSummary
 */
class Event extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'start_date', 'end_date', 'location', 'status'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Get the conversations (sessions) for this event.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the national decision drafts for this event through conversations.
     */
    public function nationalDecisionDrafts()
    {
        return $this->hasManyThrough(NationalDecisionDraft::class, Conversation::class);
    }

    /**
     * Get the master decision summary for this event.
     */
    public function masterDecisionSummary(): HasOne
    {
        return $this->hasOne(MasterDecisionSummary::class);
    }

    /**
     * Get the master decision for this event.
     */
    public function masterDecision(): HasOne
    {
        return $this->hasOne(MasterDecision::class);
    }
    public function nationalDecisionParts()
    {
        return $this->hasManyThrough(NationalDecisionPart::class, Conversation::class);
    }
}
