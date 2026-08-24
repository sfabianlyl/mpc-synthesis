<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $submitted_by_user_id
 * @property string|null $national_text
 * @property string|null $diocesan_text
 * @property string|null $consolidated_text
 * @property bool $is_submitted
 * @property Carbon|null $submitted_at
 * @property string|null $generated_by
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversation $conversation
 * @property-read User|null $submittedBy
 */
class DecisionPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'submitted_by_user_id',
        'national_text',
        'diocesan_text',
        'consolidated_text',
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
     * Get the conversation this decision part belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the pastoral user who submitted/finalized this decision part.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
