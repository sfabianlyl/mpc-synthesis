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
 * @property bool $is_master_synthesis
 * @property int|null $conversation_id
 * @property int $synthesis_id
 * @property int|null $submission_number
 * @property string|null $convergence_text
 * @property string|null $consideration_text
 * @property string|null $proposals_text
 * @property string|null $other_text
 * @property string|null $ai_convergence_text
 * @property string|null $ai_consideration_text
 * @property string|null $ai_proposals_text
 * @property string|null $ai_other_text
 * @property array|null $ai_source_group_ids
 * @property array|null $group_ids
 * @property Carbon|null $ai_generated_at
 * @property bool $llm_generated
 * @property bool $is_llm_assisted
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $synthesis
 * @property-read array<SynthesisCustomQuery> $customQueries
 */
class MasterSynthesis extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'master_syntheses';

    protected $fillable = [
        'is_master_synthesis',
        'conversation_id',
        'synthesis_id',
        'submission_number',
        'convergence_text',
        'consideration_text',
        'proposals_text',
        'other_text',
        'ai_convergence_text',
        'ai_consideration_text',
        'ai_proposals_text',
        'ai_other_text',
        'ai_source_group_ids',
        'group_ids',
        'ai_generated_at',
        'llm_generated',
        'is_llm_assisted',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_master_synthesis' => 'boolean',
            'llm_generated' => 'boolean',
            'is_llm_assisted' => 'boolean',
            'ai_source_group_ids' => 'array',
            'group_ids' => 'array',
            'ai_generated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Check if this submission is locked (cannot be edited after submission).
     */
    public function isLocked(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Get the next submission number for this conversation/user pair.
     */
    public static function getNextSubmissionNumber(int $conversationId, int $userId): int
    {
        return static::where('conversation_id', $conversationId)
            ->where('synthesis_id', $userId)
            ->max('submission_number') + 1;
    }

    /**
     * Get the conversation/session this report belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the synthesis team member who created this report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synthesis_id');
    }

    /**
     * Alias for user() - returns the synthesis team member who created this report.
     */
    public function synthesis(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Get the groups whose consensuses were synthesized.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'synthesis_group_pivot', 'synthesis_report_id', 'group_id');
    }

    /**
     * Get the custom queries for this report.
     */
    public function customQueries(): HasMany
    {
        return $this->hasMany(SynthesisCustomQuery::class);
    }

    /**
     * Scope: Only master syntheses.
     */
    public function scopeMasterSyntheses($query)
    {
        return $query->where('is_master_synthesis', true);
    }

    /**
     * Scope: Only individual syntheses.
     */
    public function scopeIndividualSyntheses($query)
    {
        return $query->where('is_master_synthesis', false);
    }

    /**
     * Get the 4 category names for this synthesis report.
     */
    public function categories(): array
    {
        return ['convergence', 'matters_for_consideration', 'proposals', 'others'];
    }

    /**
     * Check if any AI-generated content field is populated.
     */
    public function hasAiContent(): bool
    {
        return !is_null($this->ai_convergence_text)
            || !is_null($this->ai_consideration_text)
            || !is_null($this->ai_proposals_text)
            || !is_null($this->ai_other_text);
    }

    /**
     * Check if any manual content field is populated.
     */
    public function hasManualContent(): bool
    {
        return !is_null($this->convergence_text)
            || !is_null($this->consideration_text)
            || !is_null($this->proposals_text)
            || !is_null($this->other_text);
    }
}
