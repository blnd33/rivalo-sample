<?php

namespace App\Services;

use App\Enums\CheckInStatus;
use App\Enums\MatchStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use App\Models\TournamentMatch;

/**
 * Time-based reminders, run by the scheduler every few minutes. Each reminder carries a dedupe
 * key, so a player gets it once however often the job runs.
 */
class ReminderService
{
    public const MATCH_LEAD_MINUTES = 60;

    public const CHECK_IN_LEAD_HOURS = 3;

    public function __construct(private readonly Notifier $notifier) {}

    /** @return array{matches: int, check_ins: int} */
    public function run(): array
    {
        $matches = TournamentMatch::with(['participants', 'tournament'])
            ->where('status', MatchStatus::Ready->value)
            ->whereBetween('scheduled_at', [now(), now()->addMinutes(self::MATCH_LEAD_MINUTES)])
            ->get();
        foreach ($matches as $m) {
            $this->notifier->sendToEntries($m->participants->pluck('tournament_entry_id'), 'match_reminder', [
                'match' => $m->label ?? 'Round '.$m->round,
                'tournament' => $m->tournament->name,
                'time' => $m->scheduled_at->copy()->timezone($m->tournament->timezone)->format('H:i'),
            ], ['match', $m->id], "match_reminder:{$m->id}");
        }

        $checkIns = 0;
        $tournaments = Tournament::whereIn('status', [TournamentStatus::RegistrationOpen->value, TournamentStatus::RegistrationClosed->value, TournamentStatus::InProgress->value])
            ->whereBetween('check_in_closes_at', [now(), now()->addHours(self::CHECK_IN_LEAD_HOURS)])
            ->get();
        foreach ($tournaments as $t) {
            $entries = $t->entries()
                ->where('registration_status', RegistrationStatus::Reserved->value)
                ->where('check_in_status', '!=', CheckInStatus::CheckedIn->value)
                ->pluck('id');
            foreach ($entries as $entryId) {
                $this->notifier->sendToEntries([$entryId], 'check_in_reminder', [
                    'tournament' => $t->name,
                    'time' => $t->check_in_closes_at->copy()->timezone($t->timezone)->format('H:i'),
                ], ['tournament', $t->slug], "checkin_reminder:{$entryId}");
                $checkIns++;
            }
        }

        return ['matches' => $matches->count(), 'check_ins' => $checkIns];
    }
}
