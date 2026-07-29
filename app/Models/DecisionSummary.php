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
 * @property string|null $summary_text
 * @property bool $is_successful
 * @property string|null $provider
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read Diocese|null $diocese
 */
class DecisionSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'diocese_id',
        'level',
        'summary_text',
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
     * Get the conversation this decision summary belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the diocese associated with this decision summary.
     */
    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }
}
