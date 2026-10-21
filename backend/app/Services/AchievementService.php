<?php

namespace App\Services;

use App\Enums\AchievementCriteria;
use App\Enums\CheckInStatus;
use App\Enums\MatchOutcome;
use App\Enums\MatchStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Achievement;
use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Models\UserAchievement;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Badges (Milestone 6).
 *
 * Automatic badges are computed from the user's real record only: approved match results,
 * confirmed final placements and venue check-ins. They are re-checked whenever that record
 * changes; if a corrected result means a badge no longer applies, it is withdrawn. A badge that
 * staff revoked is never re-awarded automatically.
 */
class AchievementService
{
    public function __construct(private readonly Notifier $notifier) {}

    /** @return array<string, int> criteria => count */
    public function stats(User $user): array
    {
        $entryIds = TournamentEntryMember::where('user_id', $user->id)->where('is_active', true)->pluck('tournament_entry_id');

        $played = TournamentMatch::query()
            ->where('status', MatchStatus::Completed->value)
            ->whereHas('participants', fn ($q) => $q->whereIn('tournament_entry_id', $entryIds))
            ->whereHas('approvedResult', fn ($q) => $q->where('outcome', '!=', MatchOutcome::Cancelled->value))
            ->count();

        $entries = TournamentEntry::whereIn('id', $entryIds);

        return [
            AchievementCriteria::MatchesPlayed->value => $played,
            AchievementCriteria::MatchesWon->value => TournamentMatch::where('status', MatchStatus::Completed->value)->whereIn('winner_entry_id', $entryIds)->count(),
            AchievementCriteria::TournamentsCompleted->value => (clone $entries)->whereNotNull('final_place')->distinct()->count('tournament_id'),
            AchievementCriteria::PodiumFinishes->value => (clone $entries)->whereNotNull('final_place')->where('final_place', '<=', 3)->count(),
            AchievementCriteria::TournamentWins->value => (clone $entries)->where('final_place', 1)->count(),
            AchievementCriteria::CheckIns->value => (clone $entries)->where('check_in_status', CheckInStatus::CheckedIn->value)->count(),
        ];
    }

    /**
     * Award or withdraw automatic badges to match the user's current record.
     *
     * @return list<string> keys of newly awarded badges
     */
    public function evaluate(User $user): array
    {
        $stats = $this->stats($user);
        $awarded = [];
        $achievements = Achievement::where('status', 'active')->where('criteria_type', '!=', AchievementCriteria::Manual->value)->get();
        $held = UserAchievement::where('user_id', $user->id)->get()->keyBy('achievement_id');

        foreach ($achievements as $a) {
            $qualifies = ($stats[$a->criteria_type->value] ?? 0) >= $a->threshold;
            $row = $held->get($a->id);

            if ($qualifies && (! $row || ($row->revoked_at && $row->revoked_by_id === null && $row->source === 'auto'))) {
                if ($row) {
                    $row->update(['revoked_at' => null, 'revoke_reason' => null, 'awarded_at' => now()]);
                } else {
                    UserAchievement::create([
                        'user_id' => $user->id,
                        'achievement_id' => $a->id,
                        'source' => 'auto',
                        'awarded_at' => now(),
                        'context' => ['count' => $stats[$a->criteria_type->value]],
                    ]);
                }
                $awarded[] = $a->key;
                $this->notify($user, $a);
            } elseif (! $qualifies && $row && ! $row->revoked_at && $row->source === 'auto') {
                // The record changed (for example a corrected result): the badge no longer applies.
                $row->update(['revoked_at' => now(), 'revoke_reason' => 'Record changed after a correction']);
                Audit::record('achievement.auto_withdrawn', $user, new: ['achievement' => $a->key]);
            }
        }

        return $awarded;
    }

    /** Re-check everyone on these entries. Failures are reported, never thrown. */
    public function evaluateEntries(iterable $entryIds): void
    {
        $userIds = TournamentEntryMember::whereIn('tournament_entry_id', collect($entryIds)->filter()->all())->where('is_active', true)->pluck('user_id')->unique();
        foreach (User::whereIn('id', $userIds)->get() as $user) {
            try {
                $this->evaluate($user);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function awardManually(Achievement $achievement, User $user, User $staff, string $reason): UserAchievement
    {
        $this->assertCan($staff);
        if (blank($reason)) {
            throw DomainRuleException::make('reason_required', 'A reason is required.', 422);
        }

        return DB::transaction(function () use ($achievement, $user, $staff, $reason) {
            $row = UserAchievement::where('user_id', $user->id)->where('achievement_id', $achievement->id)->lockForUpdate()->first();
            if ($row && ! $row->revoked_at) {
                throw DomainRuleException::make('already_held', 'This player already has this badge.', 409);
            }
            $values = ['source' => 'manual', 'awarded_by_id' => $staff->id, 'reason' => $reason, 'awarded_at' => now(), 'revoked_at' => null, 'revoked_by_id' => null, 'revoke_reason' => null];
            $row = $row ? tap($row)->update($values) : UserAchievement::create($values + ['user_id' => $user->id, 'achievement_id' => $achievement->id]);
            Audit::record('achievement.awarded', $user, new: ['achievement' => $achievement->key], reason: $reason, actorId: $staff->id);
            $this->notify($user, $achievement);

            return $row;
        });
    }

    public function revoke(UserAchievement $row, User $staff, string $reason): UserAchievement
    {
        $this->assertCan($staff);
        if (blank($reason)) {
            throw DomainRuleException::make('reason_required', 'A reason is required.', 422);
        }
        if ($row->revoked_at) {
            throw DomainRuleException::make('already_revoked', 'This badge was already removed.', 409);
        }
        $row->update(['revoked_at' => now(), 'revoked_by_id' => $staff->id, 'revoke_reason' => $reason]);
        Audit::record('achievement.revoked', $row->user, new: ['achievement' => $row->achievement->key], reason: $reason, actorId: $staff->id);

        return $row;
    }

    /**
     * Every active badge with the user's progress, for the app.
     *
     * @return list<array<string, mixed>>
     */
    public function progress(User $user, bool $earnedOnly = false): array
    {
        $stats = $this->stats($user);
        $held = UserAchievement::where('user_id', $user->id)->whereNull('revoked_at')->get()->keyBy('achievement_id');

        return Achievement::where('status', 'active')->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn ($a) => ! $earnedOnly || $held->has($a->id))
            ->map(fn (Achievement $a) => [
                'key' => $a->key,
                'name' => $a->localizedName(),
                'description' => $a->localizedDescription(),
                'icon' => $a->icon,
                'earned' => $held->has($a->id),
                'awarded_at' => $held->get($a->id)?->awarded_at?->toIso8601String(),
                'progress' => $a->criteria_type === AchievementCriteria::Manual ? null : [
                    'current' => min($stats[$a->criteria_type->value] ?? 0, $a->threshold),
                    'target' => $a->threshold,
                ],
            ])->values()->all();
    }

    private function notify(User $user, Achievement $a): void
    {
        $previous = app()->getLocale();
        app()->setLocale($user->locale ?: 'en');
        $params = ['badge' => $a->localizedName(), 'description' => $a->localizedDescription()];
        app()->setLocale($previous);
        $this->notifier->send($user, 'achievement_unlocked', $params, ['achievements', $a->key], "achievement:{$a->id}:".now()->timestamp);
    }

    private function assertCan(User $staff): void
    {
        if (! $staff->can('content.manage') && ! $staff->can('points.manage')) {
            throw DomainRuleException::make('forbidden', 'Only staff who manage badges can do this.', 403);
        }
    }
}
