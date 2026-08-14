<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $synthesis_draft_id
 * @property string $prompt
 * @property array|null $response
 * @property string|null $provider
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read SynthesisDraft $synthesisDraft
 */
class SynthesisCustomQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'synthesis_draft_id',
        'prompt',
        'response',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
        ];
    }

    /**
     * Get the synthesis report this custom query belongs to.
     */
    public function synthesisDraft(): BelongsTo
    {
        return $this->belongsTo(synthesisDraft::class);
    }
}
