<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $is_master_synthesis
 * @property int|null $conversation_id
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
     * Get the conversation/session this report belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the custom queries for this report.
     */
    public function customQueries(): HasMany
    {
        return $this->hasMany(SynthesisCustomQuery::class, 'synthesis_report_id');
    }
}
