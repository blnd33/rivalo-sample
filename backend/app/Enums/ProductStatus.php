<?php

namespace App\Enums;

/**
 * Only active products are sold. Products with orders are archived, never deleted.
 * Seller listings (Milestone 9) go draft -> pending_review -> active, or changes_requested with
 * a note from staff; a seller takes an active listing back to draft to edit it.
 */
enum ProductStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case ChangesRequested = 'changes_requested';
    case Active = 'active';
    case Archived = 'archived';

    /** A seller may edit the listing (it is not live and not waiting for review). */
    public function sellerEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'gray',
            self::PendingReview => 'info',
            self::ChangesRequested => 'warning',
            self::Archived => 'gray',
        };
    }

    /** @return array<string, string> statuses staff set directly on official products */
    public static function officialOptions(): array
    {
        return array_intersect_key(self::options(), array_flip([self::Draft->value, self::Active->value, self::Archived->value]));
    }
}
