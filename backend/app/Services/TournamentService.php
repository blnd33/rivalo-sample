<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Enums\StaffRole;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Discipline;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Support\Audit;
use App\Support\FeatureFlags;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin-side tournament management: creation, edits, status transitions and staff assignment.
 * Players never call these methods; tournaments are created only by authorized staff.
 */
class TournamentService
{
    public function create(User $actor, array $data): Tournament
    {
        $data = $this->normalize($data);
        $this->validateConsistency($data);

        return DB::transaction(function () use ($actor, $data) {
            $tournament = new Tournament($data);
            $tournament->slug = $this->uniqueSlug($data['name']);
            $tournament->created_by_id = $actor->id;
            $tournament->status = TournamentStatus::Draft;
            $tournament->save();

            // A creator who is not a global manager is assigned as organizer so they can keep managing it.
            if (! $actor->can('tournaments.manage_all')) {
                $this->assignStaff($tournament, $actor, $actor, StaffRole::Organizer);
            }

            Audit::record('tournament.created', $tournament, new: Arr::only($tournament->getAttributes(), ['name', 'discipline_id', 'entry_fee_minor', 'currency']), actorId: $actor->id);

            return $tournament->refresh();
        });
    }

    /**
     * Update tournament fields. Once material rules are locked (registration opened), changing a
     * material field requires the manage_all permission and a written reason, and is audited.
     */
    public function update(Tournament $tournament, User $actor, array $data, ?string $reason = null): Tournament
    {
        $data = $this->normalize($data, $tournament);
        $this->validateConsistency(array_merge($tournament->getAttributes(), $data), $tournament);

        return DB::transaction(function () use ($tournament, $actor, $data, $reason) {
            $tournament = Tournament::whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            $tournament->fill($data);

            $dirty = $tournament->getDirty();
            $materialChanges = array_intersect(array_keys($dirty), Tournament::MATERIAL_FIELDS);

            if ($materialChanges && $tournament->areMaterialRulesLocked()) {
                if (! $actor->can('tournaments.manage_all')) {
                    throw DomainRuleException::make('rules_locked', 'Material rules are locked because registration has opened. Ask a super admin to make a documented correction.', 403);
                }
                if (blank($reason)) {
                    throw DomainRuleException::make('reason_required', 'A reason is required to change locked rules: :fields.', 422, ['fields' => implode(', ', array_map(fn ($f) => __(ucfirst(str_replace('_', ' ', $f))), $materialChanges))]);
                }
            }

            if (isset($dirty['entry_fee_minor']) && $tournament->entry_fee_minor > 0 && $tournament->status !== TournamentStatus::Draft) {
                $this->assertPaidEntryAllowed($tournament);
            }

            $old = Arr::only($tournament->getOriginal(), array_keys($dirty));
            $tournament->save();

            if ($dirty) {
                Audit::record(
                    $materialChanges && $tournament->areMaterialRulesLocked() ? 'tournament.locked_rules_corrected' : 'tournament.updated',
                    $tournament,
                    $this->scalarize($old),
                    $this->scalarize(Arr::only($tournament->getAttributes(), array_keys($dirty))),
                    $reason,
                    $actor->id,
                );
            }

            return $tournament;
        });
    }

    public function transition(Tournament $tournament, User $actor, TournamentStatus $target, ?string $reason = null): Tournament
    {
        return DB::transaction(function () use ($tournament, $actor, $target, $reason) {
            $tournament = Tournament::whereKey($tournament->id)->lockForUpdate()->with('discipline.sport')->firstOrFail();
            $from = $tournament->status;

            if (! $from->canTransitionTo($target)) {
                throw DomainRuleException::make('invalid_transition', 'A tournament cannot move from :from to :to.', 409, ['from' => $from->label(), 'to' => $target->label()]);
            }
            if (in_array($target, [TournamentStatus::Cancelled, TournamentStatus::Postponed], true) && blank($reason)) {
                throw DomainRuleException::make('reason_required', 'A public reason is required to cancel or postpone a tournament.');
            }

            if (in_array($target, [TournamentStatus::Published, TournamentStatus::RegistrationOpen], true)) {
                $this->assertPublishable($tournament);
            }

            if ($target === TournamentStatus::Draft && $tournament->entries()->exists()) {
                throw DomainRuleException::make('has_entries', 'A tournament with registrations cannot return to draft.', 409);
            }

            $tournament->status = $target;
            $tournament->status_reason = $reason;

            if ($target === TournamentStatus::Published && $tournament->published_at === null) {
                $tournament->published_at = now();
            }

            if ($target === TournamentStatus::RegistrationOpen && $tournament->financial_rules_locked_at === null) {
                // Lock material/financial rules once registration begins (prize rules, fees, deductions).
                $tournament->financial_rules_locked_at = now();
            }

            if ($target === TournamentStatus::RegistrationClosed) {
                // Lock all active rosters at the registration deadline.
                $tournament->entries()
                    ->whereIn('registration_status', RegistrationStatus::activeValues())
                    ->whereNull('roster_locked_at')
                    ->update(['roster_locked_at' => now()]);
            }

            $tournament->save();

            Audit::record('tournament.status_changed', $tournament, ['status' => $from->value], ['status' => $target->value], $reason, $actor->id);
            if ($target === TournamentStatus::RegistrationOpen) {
                // Players who follow this discipline hear about it once.
                $followers = DB::table('user_disciplines')->where('discipline_id', $tournament->discipline_id)->pluck('user_id');
                app(Notifier::class)->send($followers, 'new_tournament', [
                    'tournament' => $tournament->name,
                    'discipline' => $tournament->discipline?->localizedName() ?? '',
                ], ['tournament', $tournament->slug], "new_tournament:{$tournament->id}");
            }

            return $tournament;
        });
    }

    public function assignStaff(Tournament $tournament, User $staff, User $actor, StaffRole $role): TournamentStaff
    {
        $required = $role === StaffRole::Organizer ? 'tournaments.manage_assigned' : 'results.review_assigned';
        if (! $staff->can($required) && ! $staff->can('tournaments.manage_all')) {
            throw DomainRuleException::make('staff_not_eligible', 'This user does not hold a role that allows the :role assignment.', 422, ['role' => $role->label()]);
        }

        $assignment = TournamentStaff::firstOrCreate(
            ['tournament_id' => $tournament->id, 'user_id' => $staff->id, 'role' => $role->value],
            ['assigned_by_id' => $actor->id],
        );

        if ($assignment->wasRecentlyCreated) {
            Audit::record('tournament.staff_assigned', $tournament, new: ['user_id' => $staff->id, 'role' => $role->value], actorId: $actor->id);
        }

        return $assignment;
    }

    public function removeStaff(Tournament $tournament, User $staff, User $actor, StaffRole $role): void
    {
        $deleted = TournamentStaff::where('tournament_id', $tournament->id)
            ->where('user_id', $staff->id)
            ->where('role', $role->value)
            ->delete();

        if ($deleted) {
            Audit::record('tournament.staff_removed', $tournament, ['user_id' => $staff->id, 'role' => $role->value], actorId: $actor->id);
        }
    }

    // ---------------------------------------------------------------- Rules

    /** Paid-entry tournaments stay unpublishable until the compliance flag is enabled (decision D7). */
    public function assertPaidEntryAllowed(Tournament $tournament): void
    {
        if ($tournament->isPaidEntry() && ! FeatureFlags::enabled(FeatureFlags::PAID_ENTRY)) {
            throw DomainRuleException::make(
                'paid_entry_disabled',
                'Paid-entry participation is not enabled. It stays off until legal, payment and app-store eligibility is confirmed. Set the entry fee to 0 for a free tournament.',
                409,
            );
        }
    }

    private function assertPublishable(Tournament $tournament): void
    {
        $this->assertPaidEntryAllowed($tournament);

        $discipline = $tournament->discipline;
        if (! $discipline || ! $discipline->isUsableForTournaments()) {
            throw DomainRuleException::make('discipline_not_ready', 'The discipline must be active with a confirmed ruleset before the tournament can be published.', 409);
        }

        $missing = [];
        foreach (['starts_at' => 'start date', 'rules' => 'rules'] as $field => $label) {
            if (blank($tournament->{$field})) {
                $missing[] = $label;
            }
        }
        if ($tournament->registration_type !== RegistrationType::Individual && $tournament->roster_min < 2) {
            $missing[] = 'a roster minimum of at least 2 for pair/team registration';
        }
        if ($tournament->isPaidEntry() && blank($tournament->prize_rules)) {
            $missing[] = 'published prize and deduction rules';
        }
        if ($missing) {
            throw DomainRuleException::make('incomplete_tournament', 'Before publishing, add: :items.', 422, ['items' => implode(', ', array_map(fn ($m) => __($m), $missing))]);
        }
    }

    private function validateConsistency(array $data, ?Tournament $existing = null): void
    {
        $disciplineId = $data['discipline_id'] ?? $existing?->discipline_id;
        $discipline = $disciplineId ? Discipline::with('sport')->find($disciplineId) : null;
        if (! $discipline) {
            throw DomainRuleException::make('invalid_discipline', 'Choose a valid discipline.');
        }

        if ((int) ($data['sport_id'] ?? $discipline->sport_id) !== $discipline->sport_id) {
            throw DomainRuleException::make('sport_mismatch', 'The discipline does not belong to the selected sport.');
        }

        $type = $data['registration_type'] ?? null;
        $type = $type instanceof RegistrationType ? $type : RegistrationType::tryFrom((string) $type);
        if (! $type || ! $discipline->allowsRegistrationType($type)) {
            throw DomainRuleException::make('registration_type_not_allowed', 'This discipline does not allow that registration type.');
        }

        $min = (int) ($data['min_entrants'] ?? 2);
        $max = (int) ($data['max_entrants'] ?? 0);
        if ($min < 2 || $max < $min) {
            throw DomainRuleException::make('invalid_capacity', 'Minimum entrants must be at least 2 and not exceed maximum entrants.');
        }

        $rosterMin = (int) ($data['roster_min'] ?? 1);
        $rosterMax = (int) ($data['roster_max'] ?? 1);
        if ($rosterMin < 1 || $rosterMax < $rosterMin) {
            throw DomainRuleException::make('invalid_roster', 'Roster minimum must be at least 1 and not exceed roster maximum.');
        }
        if ($type === RegistrationType::Individual && ($rosterMin !== 1 || $rosterMax !== 1)) {
            throw DomainRuleException::make('invalid_roster', 'Individual tournaments have a roster of exactly one player.');
        }
        if ($type === RegistrationType::Pair && ($rosterMin !== 2 || $rosterMax !== 2)) {
            throw DomainRuleException::make('invalid_roster', 'Pair tournaments have a roster of exactly two players.');
        }

        $currency = strtoupper((string) ($data['currency'] ?? 'IQD'));
        if (! in_array($currency, Money::supportedCurrencies(), true)) {
            throw DomainRuleException::make('invalid_currency', 'Unsupported currency.');
        }
        if ((int) ($data['entry_fee_minor'] ?? 0) < 0) {
            throw DomainRuleException::make('invalid_fee', 'Entry fee cannot be negative.');
        }

        $opens = $data['registration_opens_at'] ?? null;
        $closes = $data['registration_closes_at'] ?? null;
        if ($opens && $closes && strtotime((string) $opens) >= strtotime((string) $closes)) {
            throw DomainRuleException::make('invalid_dates', 'Registration must open before it closes.');
        }
        $starts = $data['starts_at'] ?? null;
        $ends = $data['ends_at'] ?? null;
        if ($starts && $ends && strtotime((string) $starts) > strtotime((string) $ends)) {
            throw DomainRuleException::make('invalid_dates', 'The tournament must start before it ends.');
        }
    }

    private function normalize(array $data, ?Tournament $existing = null): array
    {
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }
        if (isset($data['discipline_id']) && ! isset($data['sport_id'])) {
            $data['sport_id'] = Discipline::whereKey($data['discipline_id'])->value('sport_id');
        }

        return Arr::only($data, (new Tournament)->getFillable());
    }

    private function scalarize(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v), $values);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tournament';
        $slug = $base;
        $i = 2;
        while (Tournament::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
