<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $synthesis_id
 * @property string|null $convergence_text
 * @property string|null $consideration_text
 * @property string|null $proposals_text
 * @property bool $llm_generated
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $synthesis
 */
class SynthesisReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'synthesis_id',
        'convergence_text',
        'consideration_text',
        'proposals_text',
        'llm_generated',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'llm_generated' => 'boolean',
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
     * Get the synthesis team member who created this report.
     */
    public function synthesis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synthesis_id');
    }
}
