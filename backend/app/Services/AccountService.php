<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\OrderStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SellerStatus;
use App\Enums\UserStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AccountDeletionRequest;
use App\Models\Discipline;
use App\Models\Order;
use App\Models\Report;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserNotification;
use App\Support\Audit;
use App\Support\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountService
{
    public const DELETION_GRACE_DAYS = 14;

    public const AVATAR_MAX_PX = 512;

    /** @param  list<int>  $disciplineIds */
    public function syncDisciplines(User $user, array $disciplineIds): void
    {
        $valid = Discipline::query()->active()->whereIn('id', $disciplineIds)->pluck('id')->all();
        if (count($valid) !== count(array_unique($disciplineIds))) {
            throw DomainRuleException::make('invalid_discipline', 'One or more selected sports or games are not available.');
        }

        $user->disciplines()->sync($valid);
    }

    /**
     * Stores an avatar on the public disk. When GD is available the image is re-encoded, which
     * strips EXIF metadata (including GPS location) and bounds the size.
     */
    public function updateAvatar(User $user, UploadedFile $file): string
    {
        $path = 'avatars/'.$user->id.'/'.Str::uuid().'.jpg';
        $contents = ImageSanitizer::reencode($file, self::AVATAR_MAX_PX) ?? $file->get();

        Storage::disk('public')->put($path, $contents);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => $path])->save();

        return $path;
    }

    public function removeAvatar(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }

    public function changePassword(User $user, string $current, string $new, ?int $keepTokenId = null): void
    {
        if (! Hash::check($current, $user->password)) {
            throw DomainRuleException::make('invalid_password', 'The current password is incorrect.');
        }

        $user->forceFill(['password' => $new])->save();

        // Sign out every other device after a password change.
        $user->tokens()->when($keepTokenId, fn ($q) => $q->whereKeyNot($keepTokenId))->delete();

        Audit::record('account.password_changed', $user, actorId: $user->id);
    }

    public function requestDeletion(User $user, string $password, ?string $reason = null): AccountDeletionRequest
    {
        if (! Hash::check($password, $user->password)) {
            throw DomainRuleException::make('invalid_password', 'The password is incorrect.');
        }
        if ($user->deletionRequests()->whereNull('cancelled_at')->whereNull('completed_at')->exists()) {
            throw DomainRuleException::make('deletion_pending', 'Account deletion is already scheduled.', 409);
        }
        $captainOfShared = Team::query()
            ->where('captain_id', $user->id)
            ->where('status', 'active')
            ->whereHas('activeMemberships', fn ($q) => $q->where('user_id', '!=', $user->id))
            ->pluck('name');
        if ($captainOfShared->isNotEmpty()) {
            throw DomainRuleException::make('captain_transfer_required', 'Transfer captaincy of :teams before deleting your account.', 409, ['teams' => $captainOfShared->implode(', ')]);
        }

        return DB::transaction(function () use ($user, $reason) {
            $request = $user->deletionRequests()->create([
                'reason' => $reason ? mb_substr($reason, 0, 1000) : null,
                'requested_at' => now(),
                'scheduled_for' => now()->addDays(self::DELETION_GRACE_DAYS),
            ]);
            $user->forceFill(['status' => UserStatus::PendingDeletion])->save();

            Audit::record('account.deletion_requested', $user, actorId: $user->id);

            return $request;
        });
    }

    public function cancelDeletion(User $user): void
    {
        DB::transaction(function () use ($user) {
            $pending = $user->deletionRequests()->whereNull('cancelled_at')->whereNull('completed_at')->first();
            if (! $pending) {
                throw DomainRuleException::make('no_deletion_pending', 'No account deletion is scheduled.', 404);
            }

            $pending->update(['cancelled_at' => now()]);
            $user->forceFill(['status' => UserStatus::Active])->save();

            Audit::record('account.deletion_cancelled', $user, actorId: $user->id);
        });
    }

    /**
     * Anonymizes accounts whose grace period has ended. Tournament history is kept (results stay
     * valid), but every personal field is removed and roster snapshots show "Deleted player".
     */
    public function processDueDeletions(): int
    {
        $due = AccountDeletionRequest::query()
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->where('scheduled_for', '<=', now())
            ->with('user')
            ->get();

        foreach ($due as $request) {
            $this->anonymize($request->user);
            $request->update(['completed_at' => now()]);
        }

        return $due->count();
    }

    public function anonymize(User $user): void
    {
        DB::transaction(function () use ($user) {
            $placeholder = 'deleted_'.$user->id;

            // Individual entries are withdrawn (promoting the waitlist); on team rosters the person is removed.
            $individualEntries = TournamentEntry::query()
                ->where('registered_by_id', $user->id)
                ->whereNull('team_id')
                ->whereIn('registration_status', RegistrationStatus::activeValues())
                ->get();
            foreach ($individualEntries as $entry) {
                app(RegistrationService::class)->withdraw($entry, null, 'account_deleted');
            }
            $user->entryMemberships()->where('is_active', true)
                ->whereHas('entry', fn ($q) => $q->whereIn('registration_status', RegistrationStatus::activeValues()))
                ->update(['is_active' => false, 'removed_at' => now()]);
            $user->entryMemberships()->update(['username_snapshot' => $placeholder, 'display_name_snapshot' => 'Deleted player']);

            $user->activeMemberships()->update([
                'status' => MembershipStatus::Left->value,
                'left_at' => now(),
                'left_reason' => 'account_deleted',
            ]);
            TeamInvitation::where('invited_user_id', $user->id)->where('status', 'pending')->update(['status' => 'revoked']);

            // Teams the user captained alone are disbanded (requestDeletion refuses shared captaincy).
            // If members joined during the grace period, the team stays active and staff use a forced captain transfer.
            Team::where('captain_id', $user->id)->where('status', 'active')
                ->whereDoesntHave('activeMemberships', fn ($q) => $q->where('user_id', '!=', $user->id))
                ->update(['status' => 'disbanded', 'disbanded_at' => now()]);

            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->tokens()->delete();
            $user->disciplines()->detach();

            // Inbox messages (they quote team and player names), blocks, and what reports recorded
            // about this player's profile. Reports themselves stay as the moderation record.
            UserNotification::where('user_id', $user->id)->delete();
            UserBlock::where('blocker_id', $user->id)->orWhere('blocked_id', $user->id)->delete();
            Report::where('subject_user_id', $user->id)->update(['snapshot' => json_encode(['username' => $placeholder])]);
            // Milestone 9: the shop closes with the account. Its statement stays (a financial record);
            // contact details, bio and logo are removed. Staff hand unsold stock back and settle the balance.
            $seller = Seller::where('user_id', $user->id)->first();
            if ($seller) {
                if ($seller->status !== SellerStatus::Closed) {
                    app(SellerService::class)->close($seller, null, 'Account deleted');
                }
                if ($seller->logo_path) {
                    Storage::disk('public')->delete($seller->logo_path);
                }
                $seller->forceFill(['contact_phone' => null, 'contact_email' => null, 'business_address' => null, 'bio_translations' => null, 'logo_path' => null])->save();
            }
            SellerApplication::where('user_id', $user->id)->delete();
            // Delivery details of finished orders (open orders keep them until handed over).
            Order::where('user_id', $user->id)->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                ->update(['recipient_name' => null, 'recipient_phone' => null, 'delivery_address' => null, 'customer_note' => null]);
            $user->syncRoles([]);

            $user->forceFill([
                'username' => $placeholder,
                'email' => $placeholder.'@deleted.invalid',
                'password' => Str::random(64),
                'display_name' => null,
                'avatar_path' => null,
                'city' => null,
                'phone' => null,
                'privacy' => null,
                'notification_preferences' => null,
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
                'status' => UserStatus::Deleted,
                'remember_token' => null,
            ])->save();

            Audit::record('account.anonymized', $user, actorId: null);
        });

        Log::info('Account anonymized', ['user_id' => $user->id]);
    }
}
