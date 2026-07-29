<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, DecisionDraft> $decisionDrafts
 * @property-read Collection<int, DecisionSummary> $decisionSummaries
 */
class Diocese extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    /**
     * Get the users belonging to this diocese.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'diocese_id');
    }

    /**
     * Get the decision drafts for this diocese.
     */
    public function decisionDrafts(): HasMany
    {
        return $this->hasMany(DecisionDraft::class, 'diocese_id');
    }

    /**
     * Get the decision summaries for this diocese.
     */
    public function decisionSummaries(): HasMany
    {
        return $this->hasMany(DecisionSummary::class, 'diocese_id');
    }
}
