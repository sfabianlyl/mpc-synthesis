<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property string|null $master_text
 * @property bool $is_editable
 * @property string|null $generated_by
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 */
class MasterDecisionSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'master_text',
        'is_editable',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_editable' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Get the event this master decision summary belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
