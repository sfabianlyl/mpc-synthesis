<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string|null $convergence_summary
 * @property string|null $consideration_summary
 * @property string|null $proposals_summary
 * @property string|null $other_summary
 * @property string $status
 * @property bool $is_successful
 * @property string|null $provider
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read Collection<int, MasterSynthesis> $masterSyntheses
 */
class SynthesisSummary extends Model
{
    use HasFactory;

    protected $table = 'synthesis_summaries';

    protected $fillable = [
        'conversation_id',
        'convergence_summary',
        'consideration_summary',
        'proposals_summary',
        'other_summary',
        'status',
        'is_successful',
        'provider',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_successful' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Get the conversation this summary belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get all MasterSynthesis records for this conversation.
     */
    public function masterSyntheses(): HasMany
    {
        return $this->hasMany(MasterSynthesis::class, 'conversation_id');
    }
}
