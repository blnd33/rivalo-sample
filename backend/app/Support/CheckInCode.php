<?php

namespace App\Support;

use App\Models\TournamentEntryMember;

/**
 * Signed, offline-friendly check-in codes.
 *
 * A code identifies one roster member of one entry: "{memberId}-{signature}". The signature is
 * an HMAC (app key) over the member, tournament and user, so codes cannot be guessed or forged,
 * and the same code can be shown again without a network connection. A code stops working when
 * the member is removed from the roster or the entry is no longer active (checked on scan).
 *
 * The QR code contains a URL to the admin check-in page, so staff can scan it with any phone
 * camera; the page opens only for signed-in staff (with MFA) assigned to that tournament.
 */
final class CheckInCode
{
    private const VERSION = 'v1';

    private const SIGNATURE_LENGTH = 16; // base64url chars = 96 bits

    public static function for(TournamentEntryMember $member): string
    {
        return $member->id.'-'.self::sign($member->id, $member->tournament_id, $member->user_id);
    }

    public static function url(string $code): string
    {
        return url('/admin/check-in').'?'.http_build_query(['code' => $code]);
    }

    /**
     * Accepts a bare code or a scanned URL. Returns the member when the signature is valid,
     * otherwise null. Does not check whether the entry is still active (the caller does).
     */
    public static function resolve(string $input): ?TournamentEntryMember
    {
        $code = trim($input);
        if (str_contains($code, 'code=')) {
            parse_str((string) parse_url($code, PHP_URL_QUERY), $query);
            $code = (string) ($query['code'] ?? '');
        }

        if (! preg_match('/^(\d{1,12})-([A-Za-z0-9_-]{'.self::SIGNATURE_LENGTH.'})$/', $code, $m)) {
            return null;
        }

        $member = TournamentEntryMember::with(['entry.tournament', 'user'])->find((int) $m[1]);
        if (! $member) {
            return null;
        }

        $expected = self::sign($member->id, $member->tournament_id, $member->user_id);

        return hash_equals($expected, $m[2]) ? $member : null;
    }

    private static function sign(int $memberId, int $tournamentId, int $userId): string
    {
        $mac = hash_hmac('sha256', implode('|', ['checkin', self::VERSION, $memberId, $tournamentId, $userId]), (string) config('app.key'), true);

        return substr(rtrim(strtr(base64_encode($mac), '+/', '-_'), '='), 0, self::SIGNATURE_LENGTH);
    }
}
