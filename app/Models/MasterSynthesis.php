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
 * @property array|null $group_ids
 * @property string $status
 * @property bool $is_submitted
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
        'group_ids',
        'status',
        'is_submitted',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_master_synthesis' => 'boolean',
            'group_ids' => 'array',
            'is_submitted' => 'boolean',
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
        return $this->hasMany(SynthesisCustomQuery::class, 'synthesis_report_id');
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
}
