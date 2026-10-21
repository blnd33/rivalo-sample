<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\MatchOutcome;
use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\MatchResult;
use App\Models\ResultDispute;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Match results (Milestone 5).
 *
 * - Players in the match, assigned organizers and referees can submit a result. A new submission
 *   replaces an earlier unreviewed one (kept as "superseded").
 * - Only a reviewer (assigned referee or global manager) approves. Approval completes the match,
 *   moves the winner on in the bracket, and awards points under the tournament's published
 *   points rules.
 * - Approved results are corrected by a reviewer with a reason: the old version is superseded,
 *   its points are reversed, and the bracket is updated. A correction is refused once a later
 *   match that depends on it has been played.
 * - Players can dispute an approved result within the dispute window; a reviewer upholds it (with
 *   the corrected result) or rejects it.
 */
class ResultService
{
    private const RUNNING = [TournamentStatus::RegistrationClosed, TournamentStatus::InProgress];

    public function __construct(
        private readonly FixtureService $fixtures,
        private readonly PointsService $points,
    ) {}

    public function submit(TournamentMatch $match, User $user, array $data): MatchResult
    {
        return DB::transaction(function () use ($match, $user, $data) {
            $match = $this->lockMatch($match);
            $tournament = $match->tournament;
            $isStaff = $user->can('manageFixtures', $tournament) || $user->can('reviewResults', $tournament);
            $slot = $match->slotForUser($user);

            if (! $isStaff && ($slot === null || ! $match->stage->isPublic())) {
                throw DomainRuleException::make('not_participant', 'Only players in this match or tournament staff can submit its result.', 403);
            }
            $this->assertRunning($tournament);
            if ($match->status === MatchStatus::Completed || $match->status === MatchStatus::Cancelled) {
                throw DomainRuleException::make('already_decided', 'This match already has an approved result. Raise a dispute if it is wrong.', 409);
            }
            if ($match->status !== MatchStatus::Ready) {
                throw DomainRuleException::make('match_not_ready', 'Both opponents must be known before a result can be entered.', 409);
            }

            [$outcome, $scores, $winnerSlot] = $this->normalize($match, $data, $isStaff);

            $match->results()->where('status', ResultStatus::Submitted->value)->update(['status' => ResultStatus::Superseded->value]);
            $result = MatchResult::create([
                'match_id' => $match->id,
                'version' => (int) $match->results()->max('version') + 1,
                'outcome' => $outcome,
                'scores' => $scores,
                'winner_slot' => $winnerSlot,
                'status' => ResultStatus::Submitted,
                'submitted_by_id' => $user->id,
                'submitted_as' => $isStaff ? 'staff' : 'participant',
                'note' => $data['note'] ?? null,
            ]);

            Audit::record('result.submitted', $tournament, new: [
                'match_id' => $match->id, 'version' => $result->version, 'outcome' => $outcome->value,
                'scores' => $scores, 'winner_slot' => $winnerSlot, 'as' => $result->submitted_as,
            ], actorId: $user->id);

            if (($data['approve'] ?? false) && $user->can('reviewResults', $tournament)) {
                return $this->approve($result, $user, $data['review_note'] ?? null);
            }

            return $result;
        });
    }

    public function approve(MatchResult $result, User $reviewer, ?string $note = null): MatchResult
    {
        return DB::transaction(function () use ($result, $reviewer, $note) {
            $match = $this->lockMatch($result->match);
            $tournament = $match->tournament;
            $this->assertReviewer($reviewer, $tournament);
            $this->assertRunning($tournament);

            $result = MatchResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            if ($result->status !== ResultStatus::Submitted) {
                throw DomainRuleException::make('not_pending', 'Only a submitted result can be approved.', 409);
            }
            if ($match->status !== MatchStatus::Ready) {
                throw DomainRuleException::make('match_not_ready', 'This match is not waiting for a result.', 409);
            }

            $result->update(['status' => ResultStatus::Approved, 'reviewed_by_id' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->applyToMatch($match, $result);
            $awarded = $this->points->awardForResult($result, $reviewer);

            Audit::record('result.approved', $tournament, new: ['match_id' => $match->id, 'version' => $result->version, 'points_rows' => $awarded], reason: $note, actorId: $reviewer->id);
            $this->announce($match, $result, 'result_approved');

            return $result;
        });
    }

    public function reject(MatchResult $result, User $reviewer, string $note): MatchResult
    {
        $this->assertNote($note);

        return DB::transaction(function () use ($result, $reviewer, $note) {
            $match = $this->lockMatch($result->match);
            $this->assertReviewer($reviewer, $match->tournament);
            $result = MatchResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            if ($result->status !== ResultStatus::Submitted) {
                throw DomainRuleException::make('not_pending', 'Only a submitted result can be rejected.', 409);
            }
            $result->update(['status' => ResultStatus::Rejected, 'reviewed_by_id' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
            Audit::record('result.rejected', $match->tournament, new: ['match_id' => $match->id, 'version' => $result->version], reason: $note, actorId: $reviewer->id);

            return $result;
        });
    }

    /** Replace an approved result. Points are reversed and re-awarded; the bracket follows. */
    public function correct(TournamentMatch $match, User $reviewer, array $data, string $reason): MatchResult
    {
        $this->assertNote($reason, 'A reason is required to correct an approved result.');

        return DB::transaction(function () use ($match, $reviewer, $data, $reason) {
            $match = $this->lockMatch($match);
            $tournament = $match->tournament;
            $this->assertReviewer($reviewer, $tournament);
            if ($tournament->placements_finalized_at) {
                throw DomainRuleException::make('finalized', 'Final placements are already confirmed for this tournament.', 409);
            }
            $old = $match->approvedResult;
            if (! $old || ! in_array($match->status, [MatchStatus::Completed, MatchStatus::Cancelled], true)) {
                throw DomainRuleException::make('nothing_to_correct', 'This match has no approved result to correct.', 409);
            }
            foreach ([$match->next_match_id, $match->loser_next_match_id] as $laterId) {
                if ($laterId && TournamentMatch::whereKey($laterId)->whereIn('status', [MatchStatus::Completed->value, MatchStatus::Cancelled->value])->exists()) {
                    throw DomainRuleException::make('later_match_played', 'A later match that depends on this result has already been played. Correct that match first.', 409);
                }
            }

            [$outcome, $scores, $winnerSlot] = $this->normalize($match, $data, true);

            $old->update(['status' => ResultStatus::Superseded]);
            $this->points->reverseForResult($old, $reviewer, $reason);

            $result = MatchResult::create([
                'match_id' => $match->id,
                'version' => (int) $match->results()->max('version') + 1,
                'outcome' => $outcome,
                'scores' => $scores,
                'winner_slot' => $winnerSlot,
                'status' => ResultStatus::Approved,
                'submitted_by_id' => $reviewer->id,
                'submitted_as' => 'staff',
                'note' => $reason,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            // Players in later matches may have submitted with the old opponent: those are void now.
            foreach ([$match->next_match_id, $match->loser_next_match_id] as $laterId) {
                if ($laterId) {
                    MatchResult::where('match_id', $laterId)->where('status', ResultStatus::Submitted->value)->update(['status' => ResultStatus::Superseded->value]);
                }
            }
            $match->update(['status' => MatchStatus::Ready]);
            $this->applyToMatch($match->fresh(['participants', 'stage']), $result);
            $this->points->awardForResult($result, $reviewer);

            Audit::record('result.corrected', $tournament, [
                'version' => $old->version, 'outcome' => $old->outcome->value, 'scores' => $old->scores, 'winner_slot' => $old->winner_slot,
            ], [
                'match_id' => $match->id, 'version' => $result->version, 'outcome' => $outcome->value, 'scores' => $scores, 'winner_slot' => $winnerSlot,
            ], $reason, $reviewer->id);
            $this->announce($match->fresh(['participants.entry', 'tournament']), $result, 'result_corrected');

            return $result;
        });
    }

    public function dispute(MatchResult $result, User $user, string $reason): ResultDispute
    {
        $this->assertNote($reason, 'Explain what is wrong with the result.');

        return DB::transaction(function () use ($result, $user, $reason) {
            $match = $this->lockMatch($result->match);
            $result = MatchResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            $slot = $match->slotForUser($user);

            if ($slot === null) {
                throw DomainRuleException::make('not_participant', 'Only players in this match can dispute its result.', 403);
            }
            if ($result->status !== ResultStatus::Approved) {
                throw DomainRuleException::make('not_approved', 'Only an approved result can be disputed.', 409);
            }
            $hours = (int) config('rivalo.competition.dispute_window_hours', 48);
            if ($result->reviewed_at && $result->reviewed_at->copy()->addHours($hours)->isPast()) {
                throw DomainRuleException::make('dispute_window_closed', 'The time to dispute this result has passed (:hours hours after approval).', 409, ['hours' => $hours]);
            }
            if ($result->disputes()->where('status', DisputeStatus::Open->value)->exists()) {
                throw DomainRuleException::make('dispute_open', 'A dispute about this result is already open.', 409);
            }

            $dispute = ResultDispute::create([
                'match_result_id' => $result->id,
                'match_id' => $match->id,
                'tournament_entry_id' => $match->entryIdInSlot($slot),
                'raised_by_id' => $user->id,
                'reason' => $reason,
                'status' => DisputeStatus::Open,
            ]);
            Audit::record('result.disputed', $match->tournament, new: ['match_id' => $match->id, 'dispute_id' => $dispute->id], reason: $reason, actorId: $user->id);

            return $dispute;
        });
    }

    /** Reject a dispute, or uphold it with the corrected result. */
    public function resolveDispute(ResultDispute $dispute, User $reviewer, bool $upheld, string $note, ?array $corrected = null): ResultDispute
    {
        $this->assertNote($note, 'Explain the decision.');

        return DB::transaction(function () use ($dispute, $reviewer, $upheld, $note, $corrected) {
            $dispute = ResultDispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $this->assertReviewer($reviewer, $dispute->match->tournament);
            if ($dispute->status !== DisputeStatus::Open) {
                throw DomainRuleException::make('dispute_closed', 'This dispute is already resolved.', 409);
            }
            if ($upheld) {
                if (! $corrected) {
                    throw DomainRuleException::make('correction_required', 'Enter the corrected result to uphold the dispute.', 422);
                }
                $this->correct($dispute->match, $reviewer, $corrected, __('Dispute #:id upheld: :note', ['id' => $dispute->id, 'note' => $note]));
            }
            $dispute->update([
                'status' => $upheld ? DisputeStatus::Upheld : DisputeStatus::Rejected,
                'resolved_by_id' => $reviewer->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);
            Audit::record($upheld ? 'dispute.upheld' : 'dispute.rejected', $dispute->match->tournament, new: ['dispute_id' => $dispute->id], reason: $note, actorId: $reviewer->id);
            app(Notifier::class)->send($dispute->raisedBy, $upheld ? 'dispute_upheld' : 'dispute_rejected', ['note' => $note], ['match', $dispute->match_id], "dispute:{$dispute->id}");

            return $dispute;
        });
    }

    // ---------------------------------------------------------------- Internals

    /** Tell both sides the confirmed score, and re-check their badges. */
    private function announce(TournamentMatch $match, MatchResult $result, string $type): void
    {
        $match->loadMissing(['participants.entry', 'tournament']);
        $a = $match->participants->firstWhere('slot', 1)?->entry?->display_name ?? '?';
        $b = $match->participants->firstWhere('slot', 2)?->entry?->display_name ?? '?';
        $entryIds = $match->participants->pluck('tournament_entry_id')->filter();
        app(Notifier::class)->sendToEntries($entryIds, $type, [
            'tournament' => $match->tournament->name,
            'score' => $a.' '.$result->scoreLine().' '.$b,
        ], ['match', $match->id], "result:{$result->id}");
        app(AchievementService::class)->evaluateEntries($entryIds);
    }

    private function applyToMatch(TournamentMatch $match, MatchResult $result): void
    {
        $winner = $result->winner_slot ? $match->entryIdInSlot($result->winner_slot) : null;
        $loser = $result->winner_slot ? $match->entryIdInSlot(3 - $result->winner_slot) : null;
        $cancelled = $result->outcome === MatchOutcome::Cancelled;

        $match->update([
            'status' => $cancelled ? MatchStatus::Cancelled : MatchStatus::Completed,
            'winner_entry_id' => $winner,
            'is_draw' => ! $cancelled && $result->winner_slot === null,
            'approved_result_id' => $result->id,
        ]);
        $this->fixtures->advance($match, $winner, $loser);
        $this->fixtures->completeStageIfDone($match->stage);
    }

    /**
     * Validate a result and work out the winner.
     *
     * @return array{0: MatchOutcome, 1: ?array, 2: ?int}
     */
    private function normalize(TournamentMatch $match, array $data, bool $isStaff): array
    {
        $outcome = MatchOutcome::tryFrom($data['outcome'] ?? 'normal')
            ?? throw DomainRuleException::make('invalid_outcome', 'Choose how the match ended.', 422);
        $isTable = $match->stage->type->isTable();
        $chosen = isset($data['winner_slot']) && $data['winner_slot'] !== '' ? (int) $data['winner_slot'] : null;
        if ($chosen !== null && ! in_array($chosen, [1, 2], true)) {
            throw DomainRuleException::make('invalid_winner', 'Choose the winner.', 422);
        }

        if (! $isStaff && in_array($outcome, [MatchOutcome::Cancelled, MatchOutcome::Disqualification], true)) {
            throw DomainRuleException::make('staff_only_outcome', 'Only tournament staff can record a cancellation or disqualification.', 403);
        }

        if ($outcome === MatchOutcome::Cancelled) {
            if (! $isTable) {
                throw DomainRuleException::make('knockout_needs_winner', 'A knockout match needs a winner. Record a walkover or forfeit instead.', 422);
            }

            return [$outcome, null, null];
        }

        if ($outcome !== MatchOutcome::Normal) {
            if ($chosen === null) {
                throw DomainRuleException::make('winner_required', 'Choose who wins by :outcome.', 422, ['outcome' => mb_strtolower($outcome->label())]);
            }
            $scores = isset($data['slot1'], $data['slot2']) && $data['slot1'] !== '' ? ['slot1' => $this->score($data['slot1']), 'slot2' => $this->score($data['slot2'])] : null;

            return [$outcome, $scores, $chosen];
        }

        if (! empty($data['sets'])) {
            $sets = [];
            foreach ($data['sets'] as $set) {
                $set = array_values((array) $set);
                if (count($set) !== 2) {
                    throw DomainRuleException::make('invalid_score', 'Each set needs two scores.', 422);
                }
                $sets[] = [$this->score($set[0]), $this->score($set[1])];
            }
            [$a, $b] = app(StandingsService::class)->scores(['sets' => $sets]);
            $scores = ['slot1' => $a, 'slot2' => $b, 'sets' => $sets];
        } else {
            if (! isset($data['slot1'], $data['slot2']) || $data['slot1'] === '' || $data['slot2'] === '') {
                throw DomainRuleException::make('invalid_score', 'Enter the score for both sides.', 422);
            }
            $scores = ['slot1' => $this->score($data['slot1']), 'slot2' => $this->score($data['slot2'])];
            [$a, $b] = [$scores['slot1'], $scores['slot2']];
        }

        if ($a !== $b) {
            $winner = $a > $b ? 1 : 2;
            if ($chosen !== null && $chosen !== $winner) {
                throw DomainRuleException::make('winner_mismatch', 'The chosen winner does not match the score.', 422);
            }

            return [$outcome, $scores, $winner];
        }

        // Level score: a draw in league/group play; a knockout needs a tiebreak winner (e.g. penalties).
        if ($isTable && $chosen === null) {
            return [$outcome, $scores, null];
        }
        if ($chosen === null) {
            throw DomainRuleException::make('tie_needs_winner', 'Knockout matches cannot end level. Choose who won the tiebreak.', 422);
        }

        return [$outcome, $scores, $chosen];
    }

    private function score(mixed $value): int
    {
        if (! is_numeric($value) || (int) $value != $value || (int) $value < 0 || (int) $value > 999) {
            throw DomainRuleException::make('invalid_score', 'Scores must be whole numbers from 0 to 999.', 422);
        }

        return (int) $value;
    }

    private function lockMatch(TournamentMatch $match): TournamentMatch
    {
        Tournament::whereKey($match->tournament_id)->lockForUpdate()->first();

        return TournamentMatch::whereKey($match->id)->lockForUpdate()->with(['participants', 'stage', 'tournament', 'approvedResult'])->firstOrFail();
    }

    private function assertRunning(Tournament $tournament): void
    {
        if (! in_array($tournament->status, self::RUNNING, true)) {
            throw DomainRuleException::make('tournament_not_running', 'Results can only be recorded while the tournament is being played.', 409);
        }
    }

    private function assertReviewer(User $user, Tournament $tournament): void
    {
        if (! $user->can('reviewResults', $tournament)) {
            throw DomainRuleException::make('not_reviewer', 'Only an assigned referee can approve or correct results.', 403);
        }
    }

    private function assertNote(?string $note, string $message = 'A reason is required.'): void
    {
        if (blank($note)) {
            throw DomainRuleException::make('reason_required', $message, 422);
        }
    }
}
