<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int|null $submitted_by_user_id
 * @property string|null $master_text
 * @property bool $is_submitted
 * @property Carbon|null $submitted_at
 * @property string|null $generated_by
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read User|null $submittedBy
 */
class MasterDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'submitted_by_user_id',
        'master_text',
        'is_submitted',
        'submitted_at',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_submitted' => 'boolean',
            'submitted_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Get the event this master decision belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who finalized this master decision.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
