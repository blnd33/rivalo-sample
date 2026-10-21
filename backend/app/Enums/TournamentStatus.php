<?php

namespace App\Enums;

enum TournamentStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Published = 'published';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';

    /**
     * Allowed status transitions. Anything not listed is rejected by TournamentService.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Cancelled],
            self::Published => [self::Draft, self::RegistrationOpen, self::Postponed, self::Cancelled],
            self::RegistrationOpen => [self::RegistrationClosed, self::Postponed, self::Cancelled],
            self::RegistrationClosed => [self::RegistrationOpen, self::InProgress, self::Postponed, self::Cancelled],
            self::InProgress => [self::Completed, self::Postponed, self::Cancelled],
            self::Postponed => [self::Published, self::RegistrationOpen, self::RegistrationClosed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Visible to players in the app. Drafts are staff-only. */
    public function isPublic(): bool
    {
        return $this !== self::Draft;
    }

    /** Material rules (fees, prizes, points) are locked from this point onward. */
    public function locksMaterialRules(): bool
    {
        return ! in_array($this, [self::Draft, self::Published], true);
    }
}
