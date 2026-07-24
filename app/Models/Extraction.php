<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string|null $convergence_text
 * @property string|null $consideration_text
 * @property string|null $proposals_text
 * @property string|null $provider
 * @property bool $is_successful
 * @property \Illuminate\Support\Carbon|null $extracted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User $user
 */
class Extraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'convergence_text',
        'consideration_text',
        'proposals_text',
        'provider',
        'is_successful',
        'extracted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_successful' => 'boolean',
            'extracted_at' => 'datetime',
        ];
    }

    /**
     * Get the conversation this extraction belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who performed this extraction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
