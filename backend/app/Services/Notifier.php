<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\TournamentEntryMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * In-app inbox and phone notifications (Milestone 6).
 *
 * - Every message goes to the inbox. Phone notifications follow the user's preference for the
 *   message's category; the inbox does not depend on it.
 * - Text is rendered in each user's language when sent (lang/ar.json, lang/kmr.json).
 * - Delivery runs after the surrounding database transaction commits, so a rolled-back action
 *   never notifies anyone, and a delivery failure never breaks the action itself.
 * - A dedupe key makes retries and reminders safe: the same event reaches a user once.
 */
class Notifier
{
    /** Message type => preference category that controls its phone notification. */
    public const TYPES = [
        'team_invitation' => 'team_invitations',
        'waitlist_promoted' => 'registration_updates',
        'fixtures_published' => 'registration_updates',
        'new_tournament' => 'new_tournaments',
        'check_in_reminder' => 'check_in_reminders',
        'match_reminder' => 'match_reminders',
        'match_scheduled' => 'venue_changes',
        'result_approved' => 'results',
        'result_corrected' => 'results',
        'dispute_upheld' => 'results',
        'dispute_rejected' => 'results',
        'achievement_unlocked' => 'points_and_achievements',
        'announcement' => 'news',
        // Safety and account messages: inbox only ("account" is not a phone-notification preference).
        'moderation_warning' => 'account',
        'moderation_photo_removed' => 'account',
        'moderation_logo_removed' => 'account',
        'moderation_name_reset' => 'account',
        'moderation_team_renamed' => 'account',
        'report_reviewed' => 'account',
        // Store (Milestone 8)
        'order_placed' => 'orders',
        'order_ready' => 'orders',
        'order_out_for_delivery' => 'orders',
        'order_completed' => 'orders',
        'order_cancelled' => 'orders',
        // Sellers (Milestone 9): inbox only, like other account messages.
        'seller_application_approved' => 'account',
        'seller_application_rejected' => 'account',
        'seller_listing_approved' => 'account',
        'seller_listing_changes_requested' => 'account',
        'seller_payout_recorded' => 'account',
        'seller_statement_adjusted' => 'account',
        'seller_suspended' => 'account',
        'seller_reinstated' => 'account',
        'seller_closed' => 'account',
    ];

    /** Announcement categories staff may choose (each is a user preference). */
    public const ANNOUNCEMENT_CATEGORIES = ['news', 'new_tournaments', 'registration_updates', 'venue_changes', 'match_reminders'];

    /**
     * @param  User|iterable<User>|iterable<int>  $users
     * @param  array<string, scalar|null>  $params  values for the message template
     * @param  array{0: string, 1: string|int|null}|null  $link  [kind, ref] for the app to open
     * @param  array{title?: string, body?: ?string, category?: string, announcement_id?: int, push?: bool}  $options
     */
    public function send(User|iterable $users, string $type, array $params = [], ?array $link = null, ?string $dedupeKey = null, array $options = []): void
    {
        $ids = $this->userIds($users);
        if ($ids === []) {
            return;
        }

        DB::afterCommit(function () use ($ids, $type, $params, $link, $dedupeKey, $options) {
            try {
                $this->deliver($ids, $type, $params, $link, $dedupeKey, $options);
            } catch (Throwable $e) {
                report($e); // never break the action that triggered the message
            }
        });
    }

    /** Members of tournament entries (active roster). */
    public function sendToEntries(iterable $entryIds, string $type, array $params = [], ?array $link = null, ?string $dedupeKey = null): void
    {
        $ids = collect($entryIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }
        $users = TournamentEntryMember::whereIn('tournament_entry_id', $ids)->where('is_active', true)->pluck('user_id');
        $this->send($users, $type, $params, $link, $dedupeKey);
    }

    /** @return int number of inbox messages written */
    public function deliver(array $userIds, string $type, array $params, ?array $link, ?string $dedupeKey, array $options = []): int
    {
        $category = $options['category'] ?? self::TYPES[$type] ?? 'news';
        $written = 0;
        $previousLocale = app()->getLocale();

        try {
            foreach (array_chunk($userIds, 500) as $chunk) {
                foreach (User::whereIn('id', $chunk)->get() as $user) {
                    if (! $user->isActive()) {
                        continue;
                    }
                    app()->setLocale(in_array($user->locale, ['en', 'ar', 'kmr'], true) ? $user->locale : 'en');
                    [$title, $body] = $type === 'announcement'
                        ? [$options['title'] ?? '', $options['body'] ?? null]
                        : $this->render($type, $params);

                    try {
                        $n = UserNotification::create([
                            'user_id' => $user->id,
                            'type' => $type,
                            'category' => $category,
                            'title' => mb_substr($title, 0, 160),
                            'body' => $body,
                            'link_kind' => $link[0] ?? null,
                            'link_ref' => isset($link[1]) ? (string) $link[1] : null,
                            'data' => $params ?: null,
                            'dedupe_key' => $dedupeKey,
                            'announcement_id' => $options['announcement_id'] ?? null,
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        continue; // already told this user about this event
                    }
                    $written++;

                    if (($options['push'] ?? true) && $user->wantsPush($category)) {
                        $n->update(['push_status' => 'queued']);
                        SendPushNotification::dispatch($n->id);
                    } else {
                        $n->update(['push_status' => 'skipped_preference']);
                    }
                }
            }
        } finally {
            app()->setLocale($previousLocale);
        }

        return $written;
    }

    /** @return array{0: string, 1: ?string} */
    private function render(string $type, array $p): array
    {
        $p = array_map(fn ($v) => (string) $v, $p);
        if (isset($p['match'])) {
            $p['match'] = TournamentMatch::translateLabel($p['match']); // in this user's language
        }

        return match ($type) {
            'team_invitation' => [__('Team invitation'), __(':team invited you to join the team.', $p)],
            'waitlist_promoted' => [__('You\'re in!'), __('A place opened up in :tournament. Your entry is now reserved.', $p)],
            'fixtures_published' => [__('The draw is out'), __('Fixtures for :tournament are published. See who you play.', $p)],
            'new_tournament' => [__('New tournament: :tournament', $p), __('Registration is open for :discipline.', $p)],
            'check_in_reminder' => [__('Check in before :time', $p), __('Show your check-in code at the venue for :tournament.', $p)],
            'match_reminder' => [__('Your match starts soon'), __(':match in :tournament starts at :time.', $p)],
            'match_scheduled' => [__('Match time updated'), __(':match in :tournament: :time :location', $p)],
            'result_approved' => [__('Result confirmed'), __(':tournament: :score', $p)],
            'result_corrected' => [__('Result corrected'), __(':tournament: the result is now :score', $p)],
            'dispute_upheld' => [__('Your dispute was upheld'), $p['note'] ?? null],
            'dispute_rejected' => [__('Your dispute was rejected'), $p['note'] ?? null],
            'achievement_unlocked' => [__('New badge: :badge', $p), $p['description'] ?? null],
            'moderation_warning' => [__('A message from the RIVALO team'), filled($p['message'] ?? null) ? $p['message'] : __('Please read our community guidelines. Repeated breaches can lead to suspension.')],
            'moderation_photo_removed' => [__('Profile photo removed'), __('Your profile photo was removed because it did not follow our community guidelines. You can choose another one.')],
            'moderation_logo_removed' => [__('Team logo removed'), __('The logo of :team was removed because it did not follow our community guidelines.', $p)],
            'moderation_name_reset' => [__('Display name removed'), __('Your display name was removed because it did not follow our community guidelines. Your username is shown until you choose a new one.')],
            'moderation_team_renamed' => [__('Team renamed'), __(':old is now called :team because the name did not follow our community guidelines.', $p)],
            'order_placed' => [__('Order :number placed', $p), __('We are preparing it. You pay in cash when you receive it.')],
            'order_ready' => [__('Order :number is ready', $p), __('Collect it at :place. Bring the order number; you pay in cash on pickup.', $p)],
            'order_out_for_delivery' => [__('Order :number is on its way', $p), __('Have the cash ready: you pay when it arrives.')],
            'order_completed' => [__('Order :number completed', $p), __('Thanks for supporting RIVALO.')],
            'order_cancelled' => [__('Order :number was cancelled', $p), filled($p['reason'] ?? null) ? $p['reason'] : null],
            'seller_application_approved' => [__('Welcome, :shop!', $p), __('You can now sell on RIVALO. Sign in to the Seller Hub on a computer to add your products.')],
            'seller_application_rejected' => [__('Your seller application was not approved'), filled($p['reason'] ?? null) ? $p['reason'] : null],
            'seller_listing_approved' => [__(':product is on sale', $p), __('Bring the stock to RIVALO so customers can buy it.')],
            'seller_listing_changes_requested' => [__('Changes needed: :product', $p), filled($p['note'] ?? null) ? $p['note'] : null],
            'seller_payout_recorded' => [__('Payout :number recorded', $p), __('RIVALO recorded a payment of :amount to you. Check your statement in the Seller Hub.', $p)],
            'seller_statement_adjusted' => [__('Your statement was adjusted'), __(':amount: :reason', $p)],
            'seller_suspended' => [__('Your shop is paused'), filled($p['reason'] ?? null) ? $p['reason'] : __('Your products are not shown in the store for now.')],
            'seller_reinstated' => [__('Your shop is open again'), __('Your approved products are back in the store.')],
            'seller_closed' => [__('Your shop was closed'), filled($p['reason'] ?? null) ? $p['reason'] : null],
            'report_reviewed' => match ($p['outcome'] ?? '') {
                'actioned' => [__('Thanks for your report'), __('We reviewed it and took action under our community guidelines.')],
                'dismissed' => [__('Thanks for your report'), __('We reviewed it and did not find a breach of our community guidelines.')],
                default => [__('Thanks for your feedback'), __('The RIVALO team has read your message.')],
            },
            default => [__('RIVALO'), null],
        };
    }

    /** @return list<int> */
    private function userIds(User|iterable $users): array
    {
        if ($users instanceof User) {
            return [$users->id];
        }

        return collect($users instanceof Collection ? $users->all() : $users)
            ->map(fn ($u) => $u instanceof User ? $u->id : (int) $u)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
