<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\Locales;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'username', 'email', 'password', 'display_name', 'city', 'locale', 'phone',
    'privacy', 'notification_preferences',
])]
#[Hidden([
    'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const DEFAULT_PRIVACY = [
        'show_display_name' => true,
        'show_city' => false,
        'show_teams' => true,
        'show_tournament_history' => true,
    ];

    public const DEFAULT_NOTIFICATION_PREFERENCES = [
        'new_tournaments' => true,
        'team_invitations' => true,
        'registration_updates' => true,
        'check_in_reminders' => true,
        'match_reminders' => true,
        'venue_changes' => true,
        'results' => true,
        'points_and_achievements' => true,
        'news' => false,
        'orders' => true,
    ];

    /** App languages: English, Arabic, Kurdish (Kurmanji, Latin script). See App\Support\Locales. */
    public const SUPPORTED_LOCALES = Locales::APP;

    protected $attributes = [
        'status' => 'active',
        'locale' => 'en',
        'admin_locale' => 'en',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'privacy' => 'array',
            'notification_preferences' => 'array',
            'status' => UserStatus::class,
            'is_sample_data' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->username = mb_strtolower(trim((string) $user->username));
            $user->email = mb_strtolower(trim((string) $user->email));
        });
    }

    // ---------------------------------------------------------------- Relationships

    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class, 'user_disciplines')->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', MembershipStatus::Active->value);
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'invited_user_id');
    }

    public function entryMemberships(): HasMany
    {
        return $this->hasMany(TournamentEntryMember::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(TournamentStaff::class);
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }

    // ---------------------------------------------------------------- Helpers

    public function privacySetting(string $key): bool
    {
        return (bool) (($this->privacy ?? []) + self::DEFAULT_PRIVACY)[$key];
    }

    /** @return array<string, bool> */
    public function resolvedPrivacy(): array
    {
        return array_intersect_key(($this->privacy ?? []) + self::DEFAULT_PRIVACY, self::DEFAULT_PRIVACY);
    }

    /** @return array<string, bool> */
    public function resolvedNotificationPreferences(): array
    {
        return array_intersect_key(
            ($this->notification_preferences ?? []) + self::DEFAULT_NOTIFICATION_PREFERENCES,
            self::DEFAULT_NOTIFICATION_PREFERENCES,
        );
    }

    /** Name shown publicly: display name when the user allows it, otherwise the username. */
    public function publicName(): string
    {
        return ($this->privacySetting('show_display_name') && filled($this->display_name))
            ? $this->display_name
            : $this->username;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [UserStatus::Active, UserStatus::PendingDeletion], true);
    }

    public function isStaff(): bool
    {
        return $this->hasPermissionTo(Permission::AdminAccess->value);
    }

    public function inbox(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    /** Phone notifications for this category are allowed (inbox delivery does not depend on this). */
    public function wantsPush(string $category): bool
    {
        return (bool) ($this->resolvedNotificationPreferences()[$category] ?? false);
    }

    public function isAssignedTo(Tournament $tournament, ?string $role = null): bool
    {
        return $tournament->staff()
            ->where('user_id', $this->id)
            ->when($role, fn ($q) => $q->where('role', $role))
            ->exists();
    }

    // ---------------------------------------------------------------- Notifications

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ---------------------------------------------------------------- Filament

    /**
     * Admin panel: verified, active staff. Seller Hub (Milestone 9): verified, active accounts
     * with an approved shop (suspended and closed shops can still read their statements).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== UserStatus::Active || ! $this->hasVerifiedEmail()) {
            return false;
        }

        return $panel->getId() === 'seller'
            ? $this->hasPermissionTo(Permission::SellerManageOwn->value) && Seller::where('user_id', $this->id)->exists()
            : $this->isStaff();
    }

    public function getFilamentName(): string
    {
        return $this->display_name ?: $this->username;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
