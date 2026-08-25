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
 * @property-read MasterSynthesis|null $masterSynthesis
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FinalDocument> $finalDocuments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Group> $groups
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConsensusNote> $consensusNotes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Extraction> $extractions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SynthesisDraft> $synthesisDrafts
 * @property-read SynthesisSummary $synthesisSummary
 * @property-read DioceseDecisionPart|null $dioceseDecisionPart
 * @property-read NationalDecisionPart|null $nationalDecisionPart
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
     * Get the master synthesis for this conversation.
     */
    public function masterSynthesis(): HasOne
    {
        return $this->hasOne(MasterSynthesis::class, 'conversation_id');
    }

    /**
     * Get the final documents associated with this conversation.
     */
    public function finalDocuments(): BelongsToMany
    {
        return $this->belongsToMany(FinalDocument::class, 'conversation_final_document');
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
     * Get the synthesis summary for this conversation.
     */
    public function synthesisSummary(): HasOne
    {
        return $this->hasOne(SynthesisSummary::class, 'conversation_id');
    }

    public function conversationGroups() 
    {
        return $this->hasMany(ConversationGroup::class);
    }

    /**
     * Get the diocese-level decision drafts for this conversation.
     */
    public function dioceseDecisionDrafts(): HasMany
    {
        return $this->hasMany(DioceseDecisionDraft::class);
    }

    /**
     * Get the national-level decision drafts for this conversation.
     */
    public function nationalDecisionDrafts(): HasMany
    {
        return $this->hasMany(NationalDecisionDraft::class);
    }

    /**
     * Get the diocese-level decision part for this conversation.
     */
    public function dioceseDecisionPart(): HasOne
    {
        return $this->hasOne(DioceseDecisionPart::class);
    }

    /**
     * Get the national-level decision part for this conversation.
     */
    public function nationalDecisionPart(): HasOne
    {
        return $this->hasOne(NationalDecisionPart::class);
    }
}


