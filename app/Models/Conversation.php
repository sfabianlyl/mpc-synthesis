<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Response> $responses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MasterSynthesis> $masterSynthesis
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FinalDocument> $finalDocuments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $synthesisMembers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Group> $groups
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConsensusNote> $consensusNotes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Extraction> $extractions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SynthesisDraft> $synthesisDrafts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MasterSynthesis> $masterSyntheses
 * @property-read SynthesisSummary $synthesisSummary
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DecisionDraft> $decisionDrafts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DecisionSummary> $decisionSummaries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MasterDecision> $masterDecisions
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
    public function masterSynthesis(): HasMany
    {
        return $this->hasMany(MasterSynthesis::class);
    }

    /**
     * Get the final documents associated with this conversation.
     */
    public function finalDocuments(): BelongsToMany
    {
        return $this->belongsToMany(FinalDocument::class, 'conversation_final_document');
    }
    
    /**
     * Get the synthesis members assigned to this conversation.
     */
    public function synthesisMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members', 'conversation_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the groups participating in this conversation.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'conversation_group');
    }

    /**
     * Get the consensus notes for this conversation.
     */
    public function consensusNotes(): HasMany
    {
        return $this->hasMany(ConsensusNote::class, 'conversation_id');
    }

    /**
     * Get the extractions for this conversation.
     */
    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class, 'conversation_id');
    }

    /**
     * Get the synthesis drafts for this conversation.
     */
    public function synthesisDrafts(): HasMany
    {
        return $this->hasMany(SynthesisDraft::class, 'conversation_id');
    }

    /**
     * Get the master syntheses for this conversation.
     */
    public function masterSyntheses(): HasMany
    {
        return $this->hasMany(MasterSynthesis::class, 'conversation_id');
    }

    /**
     * Get the synthesis summary for this conversation.
     */
    public function synthesisSummary(): HasOne
    {
        return $this->hasOne(SynthesisSummary::class, 'conversation_id');
    }

    /**
     * Get the decision drafts for this conversation.
     */
    public function decisionDrafts(): HasMany
    {
        return $this->hasMany(DecisionDraft::class, 'conversation_id');
    }

    /**
     * Get the decision summaries for this conversation.
     */
    public function decisionSummaries(): HasMany
    {
        return $this->hasMany(DecisionSummary::class, 'conversation_id');
    }

    /**
     * Get the master decisions for this conversation.
     */
    public function masterDecisions(): HasMany
    {
        return $this->hasMany(MasterDecision::class, 'conversation_id');
    }
}
