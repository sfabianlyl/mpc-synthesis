<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
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
class SynthesisReport extends Model
{
    use HasFactory;

    protected $fillable = [
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
        'ai_generated_at',
        'llm_generated',
        'is_llm_assisted',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'llm_generated' => 'boolean',
            'is_llm_assisted' => 'boolean',
            'ai_source_group_ids' => 'array',
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
     * Get the next submission number for this conversation/synthesis pair.
     */
    public static function getNextSubmissionNumber(int $conversationId, int $synthesisId): int
    {
        return static::where('conversation_id', $conversationId)
            ->where('synthesis_id', $synthesisId)
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
    public function synthesis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synthesis_id');
    }

    /**
     * Get the custom queries for this report.
     */
    public function customQueries(): HasMany
    {
        return $this->hasMany(SynthesisCustomQuery::class);
    }
}
