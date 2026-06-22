<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string $title
 * @property array|null $content_json
 * @property string|null $emerging_themes
 * @property string|null $exported_file_path
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Conversation> $conversations
 */
class FinalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'content_json',
        'emerging_themes',
        'exported_file_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
        ];
    }

    /**
     * Get the event this document belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who created this document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the conversations compiled in this document.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_final_document');
    }
}
