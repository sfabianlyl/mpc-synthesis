<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Group> $assignedGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SynthesisReport> $synthesisReports
 * @property-read Group|null $facilitatorGroup
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if the user has the given role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is a facilitator.
     *
     * Deprecated: Previously included 'responder' as a fallback for backward compatibility.
     * The 'responder' role has been migrated to 'facilitator'.
     */
    public function isFacilitator(): bool
    {
        return $this->hasRole('facilitator');
    }

    /**
     * Check if user is a synthesis team member.
     *
     * Deprecated: Previously included 'secretary' as a fallback for backward compatibility.
     * The 'secretary' role has been migrated to 'synthesis'.
     */
    public function isSynthesis(): bool
    {
        return $this->hasRole('synthesis');
    }

    /**
     * Check if user is a drafter.
     */
    public function isDrafter(): bool
    {
        return $this->hasRole('drafter');
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Get the groups assigned to this synthesis team member.
     */
    public function assignedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'synthesis_id');
    }

    /**
     * Get the group assigned to this facilitator.
     */
    public function facilitatorGroup(): HasOne
    {
        return $this->hasOne(Group::class, 'facilitator_id');
    }

    /**
     * Get the synthesis reports created by this synthesis team member.
     */
    public function synthesisReports(): HasMany
    {
        return $this->hasMany(SynthesisReport::class, 'synthesis_id');
    }
}
