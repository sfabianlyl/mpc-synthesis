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
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Response> $responses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SynthesisReport> $synthesisReports
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FinalDocument> $finalDocuments
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'title', 'description', 'status'];

    /**
     * Get the event this conversation belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the responses for this conversation.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * Get the synthesis reports for this conversation.
     */
    public function synthesisReports(): HasMany
    {
        return $this->hasMany(SynthesisReport::class);
    }

    /**
     * Get the final documents associated with this conversation.
     */
    public function finalDocuments(): BelongsToMany
    {
        return $this->belongsToMany(FinalDocument::class, 'conversation_final_document');
    }
}
