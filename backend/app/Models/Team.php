<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\TeamStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['sport_id', 'name', 'slug', 'logo_path', 'captain_id', 'status', 'disbanded_at'])]
class Team extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'status' => TeamStatus::class,
            'disbanded_at' => 'datetime',
            'is_sample_data' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Team $team): void {
            $team->name = trim((string) preg_replace('/\s+/', ' ', (string) $team->name));
            $team->name_normalized = mb_strtolower($team->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', MembershipStatus::Active->value);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function captainTransfers(): HasMany
    {
        return $this->hasMany(CaptainTransfer::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TournamentEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status === TeamStatus::Active;
    }

    public function isCaptain(User $user): bool
    {
        return $this->captain_id === $user->id;
    }

    public function hasActiveMember(User|int $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->activeMemberships()->where('user_id', $id)->exists();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
