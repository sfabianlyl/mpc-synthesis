<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $master_synthesis_id
 * @property int $user_id
 * @property string|null $source_category
 * @property string $key_points_text
 * @property array|null $key_points
 * @property int $requested_count
 * @property string|null $provider
 * @property string $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read MasterSynthesis $masterSynthesis
 * @property-read User $user
 */
class SynthesisKeyPoint extends Model
{
    use HasFactory;

    protected $table = 'synthesis_key_points';

    protected $fillable = [
        'conversation_id',
        'master_synthesis_id',
        'user_id',
        'source_category',
        'key_points_text',
        'key_points',
        'requested_count',
        'provider',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'requested_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the conversation this key point extraction belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the master synthesis this key point extraction belongs to.
     */
    public function masterSynthesis(): BelongsTo
    {
        return $this->belongsTo(MasterSynthesis::class);
    }

    /**
     * Get the user who triggered the extraction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Only completed key points.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Only pending key points.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
