<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $diocese_id
 * @property string $level
 * @property string|null $decision_text
 * @property bool $is_submitted
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read Diocese|null $diocese
 */
class MasterDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'diocese_id',
        'level',
        'decision_text',
        'is_submitted',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_submitted' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Get the conversation this master decision belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the diocese associated with this master decision.
     */
    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }
}
